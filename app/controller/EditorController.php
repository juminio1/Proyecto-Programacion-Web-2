<?php

namespace app\controller;

class EditorController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        $this->render->renderiza('editor');
    }
}
