<?php
function respond($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    $res = ["success" => $success, "message" => $message];
    if ($data !== null) $res["data"] = $data;
    echo json_encode($res);
    exit;
}