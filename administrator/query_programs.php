<?php 
    session_start();    
    ob_start();


    include '../db.php';

    rrms_require_login();
    rrms_require_csrf();

    // ======================


// ====================== LOAD PROGRAM LIST ======================
if (isset($_POST['loading_released_summary'])) {
    echo ''; ?>
        <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-primary btn-sm" onclick="openProgramDrawer()"><i class="bx bx-plus"></i> Add Program</button>
        </div>

        <table class="table table-sm table-striped" id="requestTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>COURSE CODE</th>
                    <th>DESCRIPTION</th>
                    <th>MAJOR</th>
                    <th width="1%"></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $select = "SELECT courseid, coursecode, coursedescription, coursemajor FROM `tblcourse` ORDER BY coursecode ASC";
                    $ruselect = mysqli_query($conn, $select);
                    $count = 0;

                    while ($r = mysqli_fetch_assoc($ruselect)) {
                        echo '
                        <tr>
                          <td class="align-middle text-end">'.++$count.'.</td>
                          <td class="align-middle">'.rrms_h($r['coursecode']).'</td>
                          <td class="align-middle">'.rrms_h($r['coursedescription']).'</td>
                          <td class="align-middle">'.rrms_h($r['coursemajor']).'</td>
                          <td class="align-middle text-nowrap">
                            <button class="btn btn-success btn-sm" onclick="edit_program('.(int) $r['courseid'].')"><i class="bx bx-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="delete_program('.(int) $r['courseid'].')"><i class="bx bx-trash"></i></button>
                          </td>
                        </tr>
                        ';
                    }
                ?>
            </tbody>
        </table>
    <?php echo '';
}


// ====================== SAVE OR UPDATE PROGRAM ======================
if (isset($_POST['save_program'])) {
    $id = trim($_POST['courseid'] ?? '');
    $code = trim($_POST['coursecode'] ?? '');
    $desc = trim($_POST['coursedescription'] ?? '');
    $major = trim($_POST['coursemajor'] ?? '');
    $college = trim($_POST['coursecollege'] ?? '');
    $spec = trim($_POST['specification'] ?? '');

    if ($id == '') {
        // Insert new
        $stmt = rrms_prepare_execute(
            $conn,
            'INSERT INTO tblcourse(coursecode, coursedescription, coursemajor, coursecollege, specification) VALUES(?, ?, ?, ?, ?)',
            'sssss',
            [$code, $desc, $major, $college, $spec]
        );
        echo $stmt ? 'success' : 'error';
    } else {
        // Update existing
        $stmt = rrms_prepare_execute(
            $conn,
            'UPDATE tblcourse SET coursecode = ?, coursedescription = ?, coursemajor = ?, coursecollege = ?, specification = ? WHERE courseid = ?',
            'sssssi',
            [$code, $desc, $major, $college, $spec, (int) $id]
        );
        echo $stmt ? 'updated' : 'error';
    }
}


// ====================== LOAD PROGRAM FOR EDIT ======================
if (isset($_POST['load_program'])) {
    $id = (int) ($_POST['courseid'] ?? 0);
    $stmt = rrms_prepare_execute($conn, 'SELECT * FROM tblcourse WHERE courseid = ?', 'i', [$id]);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $r = $result ? mysqli_fetch_assoc($result) : [];
    echo json_encode($r);
}


// ====================== DELETE PROGRAM ======================
if (isset($_POST['delete_program'])) {
    $id = (int) ($_POST['courseid'] ?? 0);

    // 🧩 Check if this course is already used in tbl_request_info
    $runcheck = rrms_prepare_execute(
        $conn,
        'SELECT COUNT(req_id) AS total FROM `tbl_request_info` WHERE req_program = ?',
        'i',
        [$id]
    );
    $checkResult = $runcheck ? mysqli_stmt_get_result($runcheck) : false;
    $checkRow = $checkResult ? mysqli_fetch_assoc($checkResult) : ['total' => 0];

    if ((int) $checkRow['total'] >= 1) {
        // If program is already used
        echo json_encode([
            'status' => 'in_use',
            'message' => 'This program cannot be deleted because it is already used in existing requests.'
        ]);
    } else {
        // Proceed with delete
        $del = rrms_prepare_execute($conn, 'DELETE FROM tblcourse WHERE courseid = ?', 'i', [$id]);
        if ($del) {
            echo json_encode([
                'status' => 'deleted',
                'message' => 'Program deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'An error occurred while deleting the program.'
            ]);
        }
    }
}


 ?>
