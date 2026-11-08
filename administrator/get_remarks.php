<?php 
    session_start();    
    ob_start();


    include '../db.php';

    rrms_require_login();
rrms_require_permission('requests.view');
    rrms_require_csrf();

    
	if (isset($_POST['req_id'])) {
	    $req_id = (int) ($_POST['req_id'] ?? 0);
	    $stmt = rrms_prepare_execute($conn, 'SELECT remarks FROM tblremarks WHERE req_id = ? LIMIT 1', 'i', [$req_id]);
	    $query = $stmt ? mysqli_stmt_get_result($stmt) : false;
	    if ($query && mysqli_num_rows($query) > 0) {
	        $row = mysqli_fetch_assoc($query);
	        echo rrms_h($row['remarks']);
	    } else {
	        echo '';
	    }
	}

 ?>
