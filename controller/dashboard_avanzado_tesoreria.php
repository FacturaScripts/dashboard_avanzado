<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of dashboard_avanzado_tesoreria
 *
 * @author carlos
 */
class dashboard_avanzado_tesoreria extends fs_controller
{
   public $year;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard Avanzado', 'admin', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->year = date('Y');
      if( isset($_REQUEST['year']) )
      {
         $this->year = $_REQUEST['year'];
      }
   }
}
