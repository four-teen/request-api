<?php 

    session_start();    
    ob_start();


    include '../db.php';
    require_once __DIR__ . '/request_status.php';
    require_once __DIR__ . '/request_documents.php';

    rrms_require_login();
    rrms_require_csrf();
    $requiredPermissions = rrms_request_action_permissions($_POST);
    if (!$requiredPermissions) rrms_access_denied(400, 'Choose a valid request action.');
    foreach ($requiredPermissions as $permission) rrms_require_permission($permission);
    if (isset($_POST['save_request']) && !rrms_can('requests.release')
        && !in_array($_POST['req_datetime_released'] ?? '', ['', '0000-00-00 00:00:00'], true)) {
        rrms_access_denied(403, 'Your account cannot create a request that is already released.');
    }



// ===========================================

date_default_timezone_set('Asia/Manila');

if (isset($_POST['request_table'])) {
    require_once __DIR__ . '/request_table.php';
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        echo json_encode(rrms_request_table_response($conn, $_POST), JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $exception) {
        error_log('reQuest paged request list failed (code ' . $exception->getCode() . ').');
        http_response_code(500);
        echo json_encode(['error' => 'Unable to load requests. Please try again.']);
    }
    exit;
}

if (isset($_POST['req_id']) && isset($_POST['remarks'])) {
    $req_id  = (int) ($_POST['req_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $dated   = date('Y-m-d H:i:s');

    $stmt = rrms_prepare_execute($conn, "
        INSERT INTO tblremarks (req_id, remarks, dated)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            remarks = VALUES(remarks),
            dated = VALUES(dated)
    ", 'iss', [$req_id, $remarks, $dated]);

    echo $stmt ? 'success' : 'Database error.';
}


if (isset($_POST['update_request'])) {
    $req_id = (int) ($_POST['req_id'] ?? 0);
    $req_lastname = trim($_POST['req_lastname'] ?? '');
    $req_firstname = trim($_POST['req_firstname'] ?? '');
    $req_middlename = trim($_POST['req_middlename'] ?? '');
    $req_ext = trim($_POST['req_ext'] ?? '');
    $req_contact_number = trim($_POST['req_contact_number'] ?? '');
    $req_due_date = str_replace('T', ' ', trim($_POST['req_due_date'] ?? ''));
    $date_processed = str_replace('T', ' ', trim($_POST['date_processed'] ?? ''));
    $prog_assigned_edit = (int) ($_POST['prog_assigned_edit'] ?? 0);

    $stmt = rrms_prepare_execute($conn, "
        UPDATE tbl_request_info SET
            req_lastname = ?,
            req_firstname = ?,
            req_middlename = ?,
            req_ext = ?,
            req_contact_number = ?,
            req_due_date = ?,
            date_processed = ?,
            prog_assigned = ?
        WHERE req_id = ?
    ", 'sssssssii', [
        $req_lastname,
        $req_firstname,
        $req_middlename,
        $req_ext,
        $req_contact_number,
        $req_due_date,
        $date_processed,
        $prog_assigned_edit,
        $req_id,
    ]);

    echo $stmt ? "success" : "error";
}


if (isset($_POST['load_edit_drawer'])) {
    $req_id = (int) ($_POST['req_id'] ?? 0);
    $select = "SELECT * FROM tbl_request_info
    LEFT JOIN tblincharge on tblincharge.inchargeid=tbl_request_info.prog_assigned
    WHERE req_id = ?";
    $stmt = rrms_prepare_execute($conn, $select, 'i', [$req_id]);
    $run = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $r = $run ? mysqli_fetch_assoc($run) : [];

    echo '
    <div class="p-3">
      <h5 class="fw-bold mb-3 text-primary"><i class="bx bx-edit"></i> Edit Request Information</h5>

      <div class="row g-3">
        <div class="col-md-6">
          <label>Last Name</label>
          <input type="text" id="edit_req_lastname" class="form-control" value="'.htmlspecialchars($r['req_lastname']).'">
        </div>
        <div class="col-md-6">
          <label>First Name</label>
          <input type="text" id="edit_req_firstname" class="form-control" value="'.htmlspecialchars($r['req_firstname']).'">
        </div>
        <div class="col-md-6">
          <label>Middle Name</label>
          <input type="text" id="edit_req_middlename" class="form-control" value="'.htmlspecialchars($r['req_middlename']).'">
        </div>
        <div class="col-md-6">
          <label>Ext.</label>
          <input type="text" id="edit_req_ext" class="form-control" value="'.htmlspecialchars($r['req_ext']).'">
        </div>

        <div class="col-md-6">
          <label>Contact Number</label>
          <input type="text" id="edit_req_contact_number" class="form-control" value="'.htmlspecialchars($r['req_contact_number']).'">
        </div>

        <div class="col-md-6">
          <label>Due Date</label>
          <input type="date" id="edit_req_due_date" class="form-control" value="'.date('Y-m-d', strtotime($r['req_due_date'])).'">
        </div>

        <div class="col-md-12">
          <label>Date Processed</label>
          <input type="datetime-local" id="edit_date_processed" class="form-control" value="'.date('Y-m-d\TH:i', strtotime($r['date_processed'])).'">
        </div>

        <div class="col-lg-12">
          <label for="prog_assigned_edit">Select Program Assigned</label>
          <select id="prog_assigned_edit" class="form-control">
          <option value="'.(int) ($r['inchargeid'] ?? 0).'">'.rrms_h($r['inchargename'] ?? '').'</option>
        '; ?>
              <?php 
                $get_ass = "SELECT inchargeid, inchargename FROM `tblincharge` ORDER BY inchargename ASC";
                $runget_ass = mysqli_query($conn, $get_ass);
                while($row_ass = mysqli_fetch_assoc($runget_ass)){
                  echo'<option value="'.(int) $row_ass['inchargeid'].'">'.rrms_h($row_ass['inchargename']).'</option>';
                }
              ?>
        <?php echo'
          </select>
        </div>        

        <div class="col-12 mt-3">
          <button class="btn btn-success w-100" onclick="update_request('.$req_id.')">
            <i class="bx bx-save"></i> Save Changes
          </button>
        </div>
      </div>
    </div>
    ';
}



if(isset($_POST['released_requested'])){

    //insert who is the program chair and remarks
    $prog_assigned = (int) ($_POST['prog_assigned'] ?? 0);
    $released_remarks = trim($_POST['released_remarks'] ?? '');
    $req_id = (int) ($_POST['req_id'] ?? 0);
    $date_released = str_replace('T', ' ', trim($_POST['date_released'] ?? ''));

    rrms_prepare_execute(
        $conn,
        'INSERT INTO `tblprogram_chair` (`request_info_id`, `programchairid`, `remarks`, `datetime_commence`) VALUES (?, ?, ?, current_timestamp())',
        'iis',
        [$req_id, $prog_assigned, $released_remarks]
    );

    rrms_prepare_execute(
        $conn,
        'UPDATE `tbl_request_info` SET req_datetime_released = ? WHERE req_id = ?',
        'si',
        [$date_released, $req_id]
    );
}


if (isset($_POST['load_details_drawer'])) {
    $req_id = (int) ($_POST['req_id'] ?? 0);
    $get_request_info = "
        SELECT * FROM tbl_request_info 
        INNER JOIN tblcourse ON tblcourse.courseid = tbl_request_info.req_program
        LEFT JOIN tblincharge on tblincharge.inchargeid=tbl_request_info.prog_assigned
        WHERE req_id = ?
    ";
    $stmt = rrms_prepare_execute($conn, $get_request_info, 'i', [$req_id]);
    $run_info = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $info = $run_info ? mysqli_fetch_assoc($run_info) : null;

    if (!$info) {
        echo '<p class="text-muted">Request not found.</p>';
        exit;
    }

    echo '<div class="mb-3">';
    echo '<h6 class="fw-bold">Requestor Information</h6>';
    echo '<p class="mb-1"><strong>Name:</strong> '.rrms_h(strtoupper($info['req_lastname']).', '.strtoupper($info['req_firstname']).' '.strtoupper($info['req_middlename'])).'</p>';
    echo '<p class="mb-1"><strong>Program:</strong> '.rrms_h($info['coursecode'].' - '.$info['coursedescription']).'</p>';
    echo '<p class="mb-1"><strong>Contact:</strong> '.rrms_h($info['req_contact_number']).'</p>';
    echo '<p class="mb-1"><strong><span class="text-primary">Date Processed:</span></strong> '.date('M d, Y h:i A', strtotime($info['date_processed'])).'</p>';
    echo '<p class="mb-1"><strong>Date Requested:</strong> '.date('M d, Y h:i A', strtotime($info['req_datetime_request'])).'</p>';
    echo '<p class="mb-1"><strong><span class="text-danger">Due Date :</span></strong> '.date('M d, Y h:i A', strtotime($info['req_due_date'])).'</p>';
    echo '<p class="mb-1"><strong>Released Date :</strong>

        '; ?>
        <?php 
        if ($info['req_datetime_released'] == '0000-00-00 00:00:00' || empty($info['req_datetime_released'])) {
           echo '<span class="badge bg-warning text-dark px-3 py-2"><i class="bx bx-time-five"></i> processing...</span>';
        }else{
            echo''.date('M d, Y h:i A', strtotime($info['req_datetime_released'])).'';
        }

        ?>
        <?php echo'
     </p>'; 
     echo '<p class="mb-1"><strong><span class="text-info">Program Assigned :</span></strong> '.rrms_h($info['inchargename']).'</p>';   
    echo '<hr>';
    echo '</div>';

    echo '<h6 class="fw-bold mb-2">Requested Documents</h6>';
    $get_docs = "
        SELECT tbldoctypes.doc_desc, tblcategory.cat_name
        FROM tblrequested_by
        INNER JOIN tbldoctypes ON tbldoctypes.id = tblrequested_by.doc_id
        INNER JOIN tblcategory ON tblcategory.catid = tbldoctypes.doc_cat
        WHERE tblrequested_by.requestorID = ?
    ";
    $docStmt = rrms_prepare_execute($conn, $get_docs, 'i', [$req_id]);
    $run_docs = $docStmt ? mysqli_stmt_get_result($docStmt) : false;
    if ($run_docs && mysqli_num_rows($run_docs) > 0) {
        echo '<ul class="list-group list-group-flush">';
        while ($doc = mysqli_fetch_assoc($run_docs)) {
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
            echo '<span><i class="bx bx-file text-primary me-2"></i> '.rrms_h($doc['cat_name'].' - '.$doc['doc_desc']).'</span>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="text-muted">No documents requested yet.</p>';
    }

if ($info['req_datetime_released'] != '0000-00-00 00:00:00') {
    echo
    '
        <div class="text-center">
            <hr><br><br>
            <div class="py-2">
                <button class="btn btn-success">Released</button>
            </div>

        </div>
    ';
} elseif (!rrms_can('requests.release')) {
    echo '<p class="text-muted mt-3">Pending release.</p>';
}else{
    echo
    '
    <div>
            <hr><br><br>
    '; ?>

    <?php echo'    
        <div class="row">
            <div class="col-lg-12">
                <label for="">Set Date and Time of Release</label>    
                <input type="datetime-local" class="form-control" id="date_released">                
            </div>
            <div class="col-lg-12">
                <label for="released_remarks">Remarks</label>
                <textarea id="released_remarks" class="form-control"></textarea>
            </div>
        </div>

        <div class="row">            
            <div class="py-2">
                <button onclick="releasing_docs(\''.$req_id.'\')" class="btn btn-danger">Release Document</button>
            </div>
        </div>
    </div>
    ';   
}

; ?> 


    <?php echo'';
}


if(isset($_POST['delete_the_requestor_requested'])){
    $reqbyid = (int) ($_POST['reqbyid'] ?? 0);
    rrms_prepare_execute($conn, 'DELETE FROM `tblrequested_by` WHERE reqbyid = ?', 'i', [$reqbyid]);
}

if(isset($_POST['save_doc_type_requestor'])){
    $get_doc_id = (int) ($_POST['get_doc_id'] ?? 0);
    $the_requestor = (int) ($_POST['the_requestor'] ?? 0);

    rrms_prepare_execute(
        $conn,
        'INSERT INTO `tblrequested_by` (`requestorID`, `doc_id`, `request_added`) VALUES (?, ?, current_timestamp())',
        'ii',
        [$the_requestor, $get_doc_id]
    );
}

if(isset($_POST['loding_req_req'])){
    $req_id = (int) ($_POST['req_id'] ?? 0);
    echo 
    ''; ?>
            <table class="table table-sm table-stripped" id="requestorTable">
                <thead>
                    <tr>
                        <td>#</td>
                        <td>DOCUMENT TYPE</td>
                        <td></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $get_the_request = "SELECT tblrequested_by.reqbyid, tblcategory.cat_name, tbldoctypes.doc_desc FROM `tblrequested_by`
                        INNER JOIN tbl_request_info on tbl_request_info.req_id=tblrequested_by.requestorID
                        INNER JOIN tbldoctypes on tbldoctypes.id=tblrequested_by.doc_id
                        INNER JOIN tblcategory on tblcategory.catid = tbldoctypes.doc_cat
                        WHERE requestorID = ?";
                        $stmt = rrms_prepare_execute($conn, $get_the_request, 'i', [$req_id]);
                        $runget_the_request = $stmt ? mysqli_stmt_get_result($stmt) : false;
                        $count = 0;
                        while($runget_the_request && ($r = mysqli_fetch_assoc($runget_the_request))){
                            echo
                            '
                                <tr>
                                    <td class="align-middle" width="1%">'.++$count.'.</td>
                                    <td class="align-middle">'.rrms_h($r['cat_name']).'</td>
                                    <td class="align-middle">'.rrms_h($r['doc_desc']).'</td>
                                    <td width="1%" class="align-middle">
                                       <div class="btn-group" role="group" aria-label="Basic mixed styles example">
                                          <button onclick="remove_requested_doc(\''.$r['reqbyid'].'\')" type="button" class="btn btn-danger btn-sm">
                                          Remove
                                          </button>

                                        </div>
                                    </td>
                                </tr>
                            ';
                        }
                    ?>

                </tbody>
            </table>
    <?php echo'';
}

if(isset($_POST['delete_the_requestor'])){
    $id = (int) ($_POST['id'] ?? 0);
    rrms_prepare_execute($conn, 'DELETE FROM `tbl_request_info` WHERE req_id = ?', 'i', [$id]);
}

if (isset($_POST['save_request'])) {

    // Get and sanitize all inputs
    $req_lastname = trim($_POST['req_lastname'] ?? '');
    $req_firstname = trim($_POST['req_firstname'] ?? '');
    $req_ext = trim($_POST['req_ext'] ?? '');
    $req_middlename = trim($_POST['req_middlename'] ?? '');
    $req_namechange = trim($_POST['req_namechange'] ?? '');
    $req_formername = trim($_POST['req_formername'] ?? '');
    $req_year_attendance = trim($_POST['req_year_attendance'] ?? '');
    $req_sem_midyear = trim($_POST['req_sem_midyear'] ?? '');
    $req_acad_year = trim($_POST['req_acad_year'] ?? '');
    $req_program = (int) ($_POST['req_program'] ?? 0);
    $req_contact_number = trim($_POST['req_contact_number'] ?? '');
    $req_datetime_request = str_replace('T', ' ', trim($_POST['req_datetime_request'] ?? ''));
    $req_due_date = str_replace('T', ' ', trim($_POST['req_due_date'] ?? ''));
    $req_datetime_released = str_replace('T', ' ', trim($_POST['req_datetime_released'] ?? ''));
    $date_processed = str_replace('T', ' ', trim($_POST['date_processed'] ?? ''));
    $prog_assigned = (int) ($_POST['prog_assigned'] ?? 0);

    if ($req_datetime_released === '') {
        $req_datetime_released = '0000-00-00 00:00:00';
    }


    // ✅ Handle ENUM fallback: if empty, default to 'No'
    if ($req_namechange == "" || ($req_namechange != "Yes" && $req_namechange != "No")) {
        $req_namechange = "No";
    }

    // ✅ SQL Insert Query
    $stmt = rrms_prepare_execute($conn, "
        INSERT INTO tbl_request_info (
            req_lastname, 
            req_firstname, 
            req_ext, 
            req_middlename, 
            req_namechange, 
            req_formername, 
            req_year_attendance, 
            req_sem_midyear, 
            req_acad_year, 
            req_program, 
            req_contact_number, 
            req_datetime_request, 
            req_due_date, 
            req_datetime_released,
            date_processed,
            prog_assigned
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", 'sssssssssisssssi', [
        $req_lastname,
        $req_firstname,
        $req_ext,
        $req_middlename,
        $req_namechange,
        $req_formername,
        $req_year_attendance,
        $req_sem_midyear,
        $req_acad_year,
        $req_program,
        $req_contact_number,
        $req_datetime_request,
        $req_due_date,
        $req_datetime_released,
        $date_processed,
        $prog_assigned,
    ]);

    if ($stmt) {
        echo "success";
    } else {
        echo "error";
    }
}



if (isset($_POST['delete_doc_type'])) {

    $id = (int) ($_POST['id'] ?? 0);
    $stmt = rrms_prepare_execute(
        $conn,
        'SELECT COUNT(reqbyid) AS total FROM `tblrequested_by` WHERE doc_id = ?',
        'i',
        [$id]
    );
    $runcheck = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $checkRow = $runcheck ? mysqli_fetch_assoc($runcheck) : ['total' => 0];

    if ((int) $checkRow['total'] >= 1) {
        // Record is used somewhere else, cannot delete
        echo json_encode([
            'status' => 'error',
            'message' => 'This document type is linked to a request and cannot be deleted.'
        ]);
    } else {
        $rundelete = rrms_prepare_execute($conn, 'DELETE FROM `tbldoctypes` WHERE id = ?', 'i', [$id]);

        if ($rundelete) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Document type has been successfully deleted.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete document type. Please try again.'
            ]);
        }
    }

    exit;
}


    if(isset($_POST['save_doc_type'])){
		$doc_cat = strtoupper(trim($_POST['doc_cat'] ?? ''));
		$doc_desc = strtoupper(trim($_POST['doc_desc'] ?? ''));
    	rrms_prepare_execute(
            $conn,
            'INSERT INTO `tbldoctypes` (`doc_cat`, `doc_desc`, `created_at`) VALUES (?, ?, current_timestamp())',
            'ss',
            [$doc_cat, $doc_desc]
        );
    }

    if(isset($_POST['loading_doc_type'])){
    	echo
    	''; ?>
    		<table class="table table-sm table-stripped" id="docttypeTable">
    			<thead>
    				<tr>
    					<td>#</td>
    					<td>DOCUMENT TYPE</td>
    					<td></td>
    				</tr>
    			</thead>
    			<tbody>
    				<?php 
				    	$select = "SELECT id, doc_desc FROM `tbldoctypes` ORDER BY doc_desc ASC";
				    	$runselect = mysqli_query($conn, $select);
				    	$count = 0;    
				    	while($r = mysqli_fetch_assoc($runselect)){
				    		echo
				    		'
		    				<tr>
		    					<td width="1%" class="align-middle">'.++$count.'.</td>
		    					<td class="align-middle">'.rrms_h($r['doc_desc']).'</td>
		    					<td width="1%" class="align-middle">
									<div class="btn-group" role="group" aria-label="Basic mixed styles example">
									  <button data-permission="documents.manage" onclick="remove_doc(\''.(int) $r['id'].'\')" type="button" class="btn btn-danger btn-sm">Remove</button>
									</div>
		    					</td>
		    				</tr>
				    		';
				    	}					
    				?>

    			</tbody>
    		</table>
    	<?php echo'';
    }


//called from summary released
if (isset($_POST['loading_released_summary'])) {
    $requestDocuments = rrms_request_document_map($conn, 'released');
    echo ''; ?>
        <table class="table table-sm table-striped request-records-table" id="requestTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="requestor-cell">REQUESTOR</th>
                    <th class="request-date-cell">REQUEST DATES</th>
                    <th class="request-documents-cell">REQUEST</th>
                    <th class="request-status-cell">STATUS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $select = "
                        SELECT r.*
                        FROM `tbl_request_info` r
                        WHERE r.req_datetime_released != '0000-00-00 00:00:00'
                        ORDER BY r.req_id DESC
                    ";
                    $ruselect = mysqli_query($conn, $select);
                    $count = 0;

                    while ($r = mysqli_fetch_assoc($ruselect)) {
                        $fullname = strtoupper($r['req_lastname']).', '.strtoupper($r['req_firstname']).' '.strtoupper($r['req_middlename']);
                        $requestDate = strtoupper(date('M d, Y h:i a', strtotime($r['req_datetime_request'])));
                        $dueDate = date('M d, Y', strtotime($r['req_due_date']));

                        echo '
                        <tr>
                            <td class="align-middle" width="1%">'.++$count.'.</td>
                            <td class="align-middle requestor-cell">
                                <div class="requestor-name">'.rrms_h($fullname).'</div>
                                <div class="request-contact-quote">
                                    <i class="bi bi-telephone"></i>
                                    <span>'.rrms_h($r['req_contact_number']).'</span>
                                </div>
                            </td>
                            <td class="align-middle request-date-cell">
                                <span class="request-date-label">Date Request</span>
                                <span class="request-date-value">'.rrms_h($requestDate).'</span>
                                <span class="request-date-label mt-2">Due Date</span>
                                <span class="request-date-value text-danger">'.rrms_h($dueDate).'</span>
                            </td>
                            <td class="align-middle request-documents-cell">'.rrms_request_documents_html($requestDocuments === false ? null : ($requestDocuments[(int) $r['req_id']] ?? [])).'</td>
                              <td class="align-middle request-status-cell">';
                        
                        // ===================== STATUS WITH WEEKEND EXCLUSION =====================
                        // ===================== STATUS WITH WEEKEND EXCLUSION (BASED ON CURRENT DATE) =====================
                        if ($r['req_datetime_released'] == '0000-00-00 00:00:00' || empty($r['req_datetime_released'])) {

                            $today = strtotime(date('Y-m-d'));
                            $dueDate = strtotime($r['req_due_date']);

                            if ($dueDate) {
                                if ($today <= $dueDate) {
                                    // Count remaining weekdays from today → due date
                                    $remainingDays = 0;
                                    $current = $today;

                                    while ($current < $dueDate) {
                                        $dayOfWeek = date('N', $current); // 1 (Mon) - 7 (Sun)
                                        if ($dayOfWeek < 6) {
                                            $remainingDays++;
                                        }
                                        $current = strtotime("+1 day", $current);
                                    }

                                    if ($remainingDays > 1) {
                                        echo '<span class="badge bg-info text-dark px-3 py-2"><i class="bx bx-calendar"></i> '.$remainingDays.' day'.($remainingDays > 1 ? 's' : '').' remaining</span>';
                                    } elseif ($remainingDays == 1) {
                                        echo '<span class="badge bg-warning text-dark px-3 py-2"><i class="bx bx-time-five"></i> Due tomorrow</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark px-3 py-2"><i class="bx bx-time-five"></i> Due today</span>';
                                    }

                                } else {
                                    // Overdue — count weekdays between due date → today
                                    $overdueDays = 0;
                                    $current = $dueDate;

                                    while ($current < $today) {
                                        $dayOfWeek = date('N', $current);
                                        if ($dayOfWeek < 6) {
                                            $overdueDays++;
                                        }
                                        $current = strtotime("+1 day", $current);
                                    }

                                    echo '<span class="badge bg-danger px-3 py-2"><i class="bx bx-error"></i> '.$overdueDays.' day'.($overdueDays > 1 ? 's' : '').' overdue</span>';
                                }
                            } else {
                                echo '<span class="badge bg-secondary px-3 py-2">No date set</span>';
                            }

                        } else {
                            // Already released
                            echo '<span class="badge bg-success"><i class="bx bx-check"></i> Released</span>';
                        }

                        // =====================================================

                        echo '</td>
                              <td width="1%" class="text-nowrap">
                                  <div class="btn-group" role="group" aria-label="Basic mixed styles example">
                                    <button onclick="showDetailsDrawer(\''.(int) $r['req_id'].'\')" type="button" class="btn btn-primary btn-sm">Details</button>
                                   
                            ';

                        echo '</div>
                              </td>
                          </tr>';
                    }
                ?>
            </tbody>
        </table>
    <?php echo '';
}

//table setup from main page
if (isset($_POST['loading_employee'])) {
    $requestListMode = ($_POST['request_status'] ?? '') === 'overdue' ? 'overdue' : 'pending';
    $requestListToday = date('Y-m-d');
    $requestListCounts = rrms_request_counts($conn, $requestListToday);
    $requestListWhere = rrms_request_list_where($requestListMode, 'r.');
    $requestDocuments = rrms_request_document_map($conn, $requestListMode, $requestListToday);
    echo ''; ?>
        <table class="table table-sm table-striped request-records-table" id="requestTable" data-request-counts="<?php echo rrms_h(json_encode($requestListCounts)); ?>">
            <thead>
                <tr>
                    <th>#</th>
                    <th></th>
                    <th class="requestor-cell">REQUESTOR</th>
                    <th class="request-date-cell">REQUEST DATES</th>
                    <th class="request-documents-cell">REQUEST</th>
                    <th class="request-status-cell">STATUS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $select = "
                        SELECT
                            r.req_id,
                            r.req_lastname,
                            r.req_firstname,
                            r.req_middlename,
                            r.req_contact_number,
                            r.req_datetime_request,
                            r.req_due_date,
                            r.req_datetime_released,
                            CASE WHEN remarks.req_id IS NULL THEN 0 ELSE 1 END AS has_remarks
                        FROM `tbl_request_info` r
                        LEFT JOIN (
                            SELECT tr.req_id
                            FROM `tblremarks` tr
                            INNER JOIN `tbl_request_info` pending_remarks ON pending_remarks.req_id = tr.req_id
                            WHERE pending_remarks.req_datetime_released = '0000-00-00 00:00:00'
                            GROUP BY tr.req_id
                        ) remarks ON remarks.req_id = r.req_id
                        WHERE $requestListWhere
                        ORDER BY r.req_id DESC
                    ";
                    $listStmt = rrms_prepare_execute($conn, $select, 's', [$requestListToday]);
                    $ruselect = $listStmt ? mysqli_stmt_get_result($listStmt) : false;
                    $count = 0;

                    // ✅ Function to count weekdays between two dates (Mon–Fri), includes due date
                    function countWeekdaysInclusive($start, $end) {
                        $count = 0;
                        $current = strtotime('+1 day', $start);
                        while ($current <= $end) {
                            $day = date('N', $current); // 1=Mon ... 7=Sun
                            if ($day < 6) $count++;
                            $current = strtotime('+1 day', $current);
                        }
                        return $count; // ✅ include due date
                    }

                    while ($ruselect && ($r = mysqli_fetch_assoc($ruselect))) {
                        $fullname = strtoupper($r['req_lastname']).', '.strtoupper($r['req_firstname']).' '.strtoupper($r['req_middlename']);
                        $requestDate = strtoupper(date('M d, Y h:i a', strtotime($r['req_datetime_request'])));
                        $dueDate = date('M d, Y', strtotime($r['req_due_date']));

                        echo '
                        <tr>
                            <td class="align-middle" width="1%">'.++$count.'.</td>
                            <td class="align-middle" width="1%">
                        '; ?>
                        <?php 

                            $hasRemarks = (int) $r['has_remarks'] === 1;
                            $btnClass = $hasRemarks ? 'btn-info' : 'btn-secondary';
echo '

  <button onclick="add_remarks(\''.(int) $r['req_id'].'\')" 
          type="button" class="btn '.$btnClass.' btn-sm" title="Remarks">
      <i class="bi bi-info-square"></i>
  </button>
';
                        ?>
                        <?php echo'

                            </td>
                            <td class="align-middle requestor-cell">
                                <div class="requestor-name">'.rrms_h($fullname).'</div>
                                <div class="request-contact-quote">
                                    <i class="bi bi-telephone"></i>
                                    <span>'.rrms_h($r['req_contact_number']).'</span>
                                </div>
                            </td>
                            <td class="align-middle request-date-cell">
                                <span class="request-date-label">Date Request</span>
                                <span class="request-date-value">'.rrms_h($requestDate).'</span>
                                <span class="request-date-label mt-2">Due Date</span>
                                <span class="request-date-value text-danger">'.rrms_h($dueDate).'</span>
                            </td>
                            <td class="align-middle request-documents-cell">'.rrms_request_documents_html($requestDocuments === false ? null : ($requestDocuments[(int) $r['req_id']] ?? [])).'</td>
                              <td class="align-middle request-status-cell">';
                        
                        // ===================== STATUS (Excludes Weekends + Adds +1 Day) =====================
                        if ($r['req_datetime_released'] == '0000-00-00 00:00:00' || empty($r['req_datetime_released'])) {

                            echo rrms_due_status_badge($r['req_due_date']);

                        } else {
                            // Already released
                            echo '<span class="badge bg-success"><i class="bx bx-check"></i> Released</span>';
                        }
                        // =====================================================

                        echo '</td>
                              <td class="align-middle request-actions-cell">
                                  <div class="request-row-actions" role="group" aria-label="Request actions">
                                    <button onclick="showDetailsDrawer(\''.(int) $r['req_id'].'\')" type="button" class="btn btn-primary btn-sm" title="Details" aria-label="View request details"><i class="bi bi-menu-down" aria-hidden="true"></i></button>
                                    <button onclick="editRequestDrawer(\''.(int) $r['req_id'].'\')" type="button" class="btn btn-success btn-sm" title="Edit" aria-label="Edit request"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>';

                        // Buttons for non-released requests
                        if ($r['req_datetime_released'] == '0000-00-00 00:00:00' || empty($r['req_datetime_released'])) {
                            echo '
                                <button onclick="request_docs(\''.(int) $r['req_id'].'\', '.rrms_h(json_encode($fullname)).')" type="button" class="btn btn-warning btn-sm" title="Request" aria-label="Manage requested documents"><i class="bi bi-chat-square-dots-fill" aria-hidden="true"></i></button>
                                <button onclick="remove_requestor(\''.(int) $r['req_id'].'\')" type="button" class="btn btn-danger btn-sm" title="Delete" aria-label="Delete request"><i class="bi bi-trash-fill" aria-hidden="true"></i></button>';
                        }

                        echo '</div>
                              </td>
                          </tr>';
                    }
                ?>
            </tbody>
        </table>
    <?php echo '';
}


 ?>
