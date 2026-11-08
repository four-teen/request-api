<?php 
    session_start();    
    ob_start();


    include '../db.php';

    rrms_require_login();
    rrms_require_permission('statistics.view');
    rrms_require_csrf();

	$query = "
	    SELECT 
	        d.doc_desc AS document_name,
	        COUNT(r.reqbyid) AS total_requests
	    FROM tblrequested_by r
	    INNER JOIN tbldoctypes d ON d.id = r.doc_id
	    GROUP BY d.id
	    ORDER BY total_requests DESC
	";

	$result = mysqli_query($conn, $query);

	$data = [];
	while ($row = mysqli_fetch_assoc($result)) {
	    $data[] = [
	        'document' => $row['document_name'],
	        'count' => (int)$row['total_requests']
	    ];
	}

	header('Content-Type: application/json');
	echo json_encode($data);


?>

