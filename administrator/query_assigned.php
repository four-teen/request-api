<?php 
session_start();    
ob_start();
include '../db.php';

rrms_require_login();
rrms_require_csrf();

// ====================== LOAD FACULTY LIST ======================
if (isset($_POST['loading_assigned_fac'])) {
    echo ''; ?>
        <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-primary btn-sm" onclick="openFacultyDrawer()">
            <i class="bx bx-plus"></i> Add Faculty
          </button>
        </div>

        <table class="table table-sm table-striped" id="requestTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>FACULTY FULL NAME</th>
                    <th width="1%"></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $select = "SELECT inchargeid, inchargename FROM `tblincharge` ORDER BY inchargename ASC";
                    $ruselect = mysqli_query($conn, $select);
                    $count = 0;

                    while ($r = mysqli_fetch_assoc($ruselect)) {
                        echo '
                        <tr>
                          <td width="1%" class="align-middle text-end">'.++$count.'.</td>
                          <td class="align-middle">'.rrms_h(strtoupper($r['inchargename'])).'</td>
                          <td class="align-middle text-nowrap">
                            <button class="btn btn-success btn-sm" onclick="edit_faculty('.(int) $r['inchargeid'].')"><i class="bx bx-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="delete_faculty('.(int) $r['inchargeid'].')"><i class="bx bx-trash"></i></button>
                          </td>
                        </tr>
                        ';
                    }
                ?>
            </tbody>
        </table>
    <?php echo '';
}



// ====================== SAVE OR UPDATE FACULTY ======================
if (isset($_POST['save_faculty'])) {
    $id = trim($_POST['inchargeid'] ?? '');
    $name = trim($_POST['inchargename'] ?? '');

    if ($id == '') {
        // insert
        $stmt = rrms_prepare_execute($conn, 'INSERT INTO tblincharge(inchargename) VALUES(?)', 's', [$name]);
        echo $stmt ? 'success' : 'error';
    } else {
        // update
        $stmt = rrms_prepare_execute($conn, 'UPDATE tblincharge SET inchargename = ? WHERE inchargeid = ?', 'si', [$name, (int) $id]);
        echo $stmt ? 'updated' : 'error';
    }
}



// ====================== LOAD ONE RECORD FOR EDIT ======================
if (isset($_POST['load_faculty'])) {
    $id = (int) ($_POST['inchargeid'] ?? 0);
    $stmt = rrms_prepare_execute($conn, 'SELECT * FROM tblincharge WHERE inchargeid = ?', 'i', [$id]);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $r = $result ? mysqli_fetch_assoc($result) : [];
    echo json_encode($r);
}



// ====================== DELETE FACULTY ======================
// ====================== DELETE FACULTY (No validation) ======================
if (isset($_POST['delete_faculty'])) {
    $id = (int) ($_POST['inchargeid'] ?? 0);

    // Make sure ID is not empty and numeric
    if ($id > 0) {
        $del = rrms_prepare_execute($conn, 'DELETE FROM tblincharge WHERE inchargeid = ?', 'i', [$id]);

        if ($del) {
            echo json_encode([
                'status' => 'deleted',
                'message' => 'Faculty deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database delete failed.'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid faculty ID.'
        ]);
    }
}

?>
