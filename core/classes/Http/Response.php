<?php

namespace Http;

class Response {
    public function status($code) {
        http_response_code((int)$code);
    }

    public function header($name, $value, $replace = true, $code = 0) {
        if ($code) {
            header($name . ': ' . $value, $replace, (int)$code);
            return;
        }
        header($name . ': ' . $value, $replace);
    }

    public function redirect($url, $code = 302) {
        logger()->debug('Redirect', array('url' => $url, 'code' => $code));
        header('Location: ' . $url, true, (int)$code);
        exit;
    }

    public function json($data, $code = 200) {
        $this->status((int)$code);
        $this->header('Content-Type', 'application/json');
        echo json_encode($data);
        exit;
    }
}
