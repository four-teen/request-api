<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/../db.php';
require __DIR__ . '/account_functions.php';
function rrms_account_reply($status, $data) { http_response_code($status); echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
$actor = rrms_current_user();
if (!$actor) rrms_account_reply(401, ['message' => 'Your session expired. Sign in again.']);
if (!rrms_user_can($actor, 'administration')) rrms_account_reply(403, ['message' => 'Only administrators can manage accounts.']);
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { header('Allow: GET, POST'); rrms_account_reply(405, ['message' => 'Method not allowed.']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !rrms_verify_csrf(rrms_request_csrf_token())) rrms_account_reply(419, ['message' => 'Your security token expired. Refresh the page.']);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        rrms_account_reply(200, ['accounts' => rrms_accounts_list($conn), 'chairs' => rrms_account_rows($conn, 'SELECT inchargeid AS id, inchargename AS name FROM tblincharge ORDER BY inchargename'),
            'functions' => rrms_permission_catalog(), 'currentId' => $actor['id']]);
    }
    if (($_POST['action'] ?? '') !== 'save') throw new InvalidArgumentException('Unknown account action.');
    $id = rrms_account_save($conn, $actor, $_POST);
    $self = $id === $actor['id'];
    if ($self) {
        session_regenerate_id(true);
        rrms_set_login_session(rrms_account_principal($conn, $id));
        unset($_SESSION['csrf_token']);
    }
    rrms_account_reply(200, ['message' => 'Account saved.', 'id' => $id, 'selfUpdated' => $self, 'csrfToken' => rrms_csrf_token()]);
} catch (RrmsAccountForbidden $error) { rrms_account_reply(403, ['message' => $error->getMessage()]);
} catch (InvalidArgumentException $error) { rrms_account_reply(422, ['message' => $error->getMessage()]);
} catch (DomainException $error) { rrms_account_reply(409, ['message' => $error->getMessage()]);
} catch (mysqli_sql_exception $error) {
    if ($error->getCode() === 1062) rrms_account_reply(409, ['message' => 'That username or program chair already has an account.']);
    error_log('reQuest account save failed (code ' . $error->getCode() . ').');
    rrms_account_reply(503, ['message' => 'Unable to load or save accounts. Please try again.']);
} catch (Throwable $error) {
    error_log('reQuest account management failed: ' . get_class($error));
    rrms_account_reply(503, ['message' => 'Unable to load or save accounts. Please try again.']);
}
