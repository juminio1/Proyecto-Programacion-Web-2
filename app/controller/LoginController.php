<?php

namespace app\controller;

class LoginController{

    private $render;

    public function __construct($render){
        $this->render = $render;
    }

    public function show() {
        $this->render->renderiza("login");
    }

}