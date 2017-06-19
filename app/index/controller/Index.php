<?php 
namespace app\index\controller;

use fire\Controller;

class Index extends Controller{

    public function show() {
        $this->result["data"]="Welcome to fire World!";
        
        return $this->result;
    }
}