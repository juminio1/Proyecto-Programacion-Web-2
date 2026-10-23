<?php

namespace app\controller;

class PartidaController{

    private $partidaModel;
    private $render;

    public function __construct($partidaModel, $render){
        $this->partidaModel = $partidaModel;
        $this->render = $render;
    }

    public function show(){
        $this->render->renderiza("partida");
    }

}