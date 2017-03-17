<?php

/*
 * This file is part of dashboard_avanzado
 * Copyright (C) 2017		 Itaca Software Libre contacta@itacaswl.com
 * Copyright (C) 2017      Carlos Garcia Gomez   neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('subcuenta.php');

/**
 * Description of dashboard_avanzado_tesoreria
 *
 * @author Carlos garcía Gómez
 * @author juanguinho - Itaca Software Libre
 */
class dashboard_avanzado_tesoreria extends fs_controller
{
   public $bancos;
   public $cajas;
   public $config;
   public $da_tesoreria;
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

      $fsvar = new fs_var();
      $this->config = json_decode($fsvar->simple_get('dashboard_avanzado_config'), true);
      
      $this->get_bancos();
      $this->get_cajas();
      
      // Definimos estructura para tesorería		
      $this->da_tesoreria = array(
          'desde' => date('1-1-' . $this->year),
          'hasta' => date('31-12-' . $this->year),
          'total_cajas' => 0,
          'total_bancos' => 0,
          'total_tesoreria' => 0,
      );
      
      foreach($this->cajas as $caja)
      {
         $this->da_tesoreria["total_cajas"] += $caja->saldo;
      }
      
      foreach($this->bancos as $banco)
      {
         $this->da_tesoreria["total_bancos"] += $banco->saldo;
      }

      $this->da_tesoreria["total_tesoreria"] = $this->da_tesoreria["total_cajas"] + $this->da_tesoreria["total_bancos"];

      /// Definimos estructura para gastoscobros		
      $this->da_gastoscobros = array(
          'desde' => date('1-1-' . $this->year),
          'hasta' => date('31-12-' . $this->year),
          'gastospdtepago' => 0,
          'clientespdtecobro' => 0,
          'nominaspdtepago' => 0,
          'segsocialpdtepago' => 0,
          'segsocialpdtecobro' => 0,
          'total_gastoscobros' => 0,
      );

      $this->da_gastoscobros["gastospdtepago"] = -1 * $this->saldo_cuenta('40%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["gastospdtepago"] += -1 * $this->saldo_cuenta('41%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["clientespdtecobro"] = $this->saldo_cuenta('43%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["nominaspdtepago"] = $this->saldo_cuenta('465%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["segsocialpdtepago"] = -1 * $this->saldo_cuenta('476%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["segsocialpdtecobro"] = $this->saldo_cuenta('471%', $this->da_gastoscobros["desde"], $this->da_gastoscobros["hasta"]);
      $this->da_gastoscobros["total_gastoscobros"] = $this->da_gastoscobros["gastospdtepago"] + $this->da_gastoscobros["clientespdtecobro"] +
              $this->da_gastoscobros["nominaspdtepago"] + $this->da_gastoscobros["segsocialpdtepago"] + $this->da_gastoscobros["segsocialpdtecobro"];
      
      /// Definimos estructura para reservasresultados		
      $this->da_reservasresultados = array(
          'desde' => date('1-1-' . $this->year),
          'hasta' => date('31-12-' . $this->year),
          'reservalegal' => 0,
          'reservasvoluntarias' => 0,
          'resultadoejercicioanterior' => 0,
          'total_reservas' => 0,
      );

      $this->da_reservasresultados["reservalegal"] = $this->saldo_cuenta('112%', $this->da_reservasresultados["desde"], $this->da_reservasresultados["hasta"]);
      $this->da_reservasresultados["reservasvoluntarias"] = $this->saldo_cuenta('113%', $this->da_reservasresultados["desde"], $this->da_reservasresultados["hasta"]);
      $this->da_reservasresultados["resultadoejercicioanterior"] = $this->saldo_cuenta('121%', $this->da_reservasresultados["desde"], $this->da_reservasresultados["hasta"]);
      $this->da_reservasresultados["total_reservas"] = $this->da_reservasresultados["reservalegal"] + $this->da_reservasresultados["reservasvoluntarias"] +
              $this->da_reservasresultados["resultadoejercicioanterior"];
      
      /// Definimos estructura para resultado ejercicio actual		
      $this->da_resultadoejercicioactual = array(
          'desde' => date('1-1-' . $this->year),
          'hasta' => date('31-12-' . $this->year),
          'total_ventas' => 0,
          'total_gastos' => 0,
          'resultadoexplotacion' => 0,
          'amortizacioninmovintang' => 0,
          'amortizacioninmovmat' => 0,
          'total_amort' => 0,
          'resultado_antes_impuestos' => 0,
          'impuesto_sociedades' => 0,
          'resultado_despues_impuestos' => 0,
      );

      $this->da_resultadoejercicioactual["total_ventas"] = $this->saldo_cuenta('7%', $this->da_resultadoejercicioactual["desde"], $this->da_resultadoejercicioactual["hasta"]);
      $this->da_resultadoejercicioactual["total_gastos"] = -1 * $this->saldo_cuenta('6%', $this->da_resultadoejercicioactual["desde"], $this->da_resultadoejercicioactual["hasta"]);
      $this->da_resultadoejercicioactual["resultadoexplotacion"] = $this->da_resultadoejercicioactual["total_ventas"] + $this->da_resultadoejercicioactual["total_gastos"];
      $this->da_resultadoejercicioactual["amortizacioninmovintang"] = -1 * $this->saldo_cuenta('680%', $this->da_resultadoejercicioactual["desde"], $this->da_resultadoejercicioactual["hasta"]);
      $this->da_resultadoejercicioactual["amortizacioninmovmat"] = -1 * $this->saldo_cuenta('681%', $this->da_resultadoejercicioactual["desde"], $this->da_resultadoejercicioactual["hasta"]);
      $this->da_resultadoejercicioactual["total_amort"] = $this->da_resultadoejercicioactual["amortizacioninmovintang"] + $this->da_resultadoejercicioactual["amortizacioninmovmat"];
      $this->da_resultadoejercicioactual["resultado_antes_impuestos"] = $this->da_resultadoejercicioactual["resultadoexplotacion"] + $this->da_resultadoejercicioactual["total_amort"];

      if($this->da_resultadoejercicioactual["resultado_antes_impuestos"] < 0)
      {
         $this->da_resultadoejercicioactual["impuesto_sociedades"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->year]['sociedades'];
         $this->da_resultadoejercicioactual["impuesto_sociedades"] = -1 * $this->da_resultadoejercicioactual["resultado_antes_impuestos"] * $sociedades / 100;
      }

      $this->da_resultadoejercicioactual["resultado_despues_impuestos"] = $this->da_resultadoejercicioactual["resultado_antes_impuestos"]
              + $this->da_resultadoejercicioactual["impuesto_sociedades"];

      /// Definimos estructura para impuestos		
      $this->da_impuestos = array(
          'desde' => date('1-1-' . $this->year),
          'hasta' => date('31-12-' . $this->year),
          'irpf-mod111' => 0,
          'irpf-mod115' => 0,
          'iva-repercutido' => 0,
          'iva-soportado' => 0,
          'iva-devolver' => 0,
          'resultado_iva-mod303' => 0,
          'ventas_totales' => 0,
          'gastos_totales' => 0,
          'resultado' => 0,
          'sociedades' => 0,
          'pago-ant' => 0,
          'pagofraccionado-mod202' => 0,
          'resultado_ejanterior' => 0,
          'resultado_negotros' => 0,
          'total' => 0,
          'sociedades_ant' => 0,
          'sociedades_adelantos' => 0,
          'total-mod200' => 0,
      );
      
      /// ahora hay que calcular las fechas del trimestre para los primeros bloques de impuestos
      switch (date('m'))
      {
         case '1':
         case '2':
         case '3':
         case '4':
            $this->da_impuestos['desde'] = date('1-1-' . $this->year);
            $this->da_impuestos['hasta'] = date('t-3-' . $this->year);
            break;

         case '5':
         case '6':
         case '7':
            $this->da_impuestos['desde'] = date('1-4-' . $this->year);
            $this->da_impuestos['hasta'] = date('t-6-' . $this->year);
            break;

         case '8':
         case '9':
         case '10':
            $this->da_impuestos['desde'] = date('1-7-' . $this->year);
            $this->da_impuestos['hasta'] = date('t-9-' . $this->year);
            break;

         case '11':
         case '12':
            $this->da_impuestos['desde'] = date('1-10-' . $this->year);
            $this->da_impuestos['hasta'] = date('t-12-' . $this->year);
            break;
      }
      
      $this->da_impuestos["irpf-mod111"] = -1 * $this->saldo_cuenta('4751%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);

      // cogemos las cuentas del alquiler de la configuración para generar el mod-115
      if(isset($this->config[$this->year]['irpfalquiler']))
      {
         $cuentasalquiler = explode(",", $this->config[$this->year]['irpfalquiler']);

         foreach($cuentasalquiler as $cuentaalquiler)
         {
            if(isset($cuentaalquiler))
            {
               $this->da_impuestos["irpf-mod115"] += -1 * $this->saldo_cuenta($cuentaalquiler, $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
            }
         }
      }

      $this->da_impuestos["iva-repercutido"] = -1 * $this->saldo_cuenta('477%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["iva-soportado"] = $this->saldo_cuenta('472%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["iva-devolver"] = $this->saldo_cuenta('470%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["resultado_iva-mod303"] = $this->da_impuestos["iva-repercutido"] + $this->da_impuestos["iva-soportado"]
              + $this->da_impuestos["iva-devolver"];

      /// ahora hay que calcular las fechas para impuestos especiales que vienen a continuación
      switch (date('m'))
      {
         case '1':
         case '2':
         case '3':
         case '4':
            $this->da_impuestos['desde'] = date('1-1-' . $this->year);
            $this->da_impuestos['hasta'] = date('31-3-' . $this->year);
            break;

         case '5':
         case '6':
         case '7':
         case '8':
         case '9':
         case '10':
            $this->da_impuestos['desde'] = date('1-1-' . $this->year);
            $this->da_impuestos['hasta'] = date('30-9-' . $this->year);
            break;

         case '11':
         case '12':
            $this->da_impuestos['desde'] = date('1-1-' . $this->year);
            $this->da_impuestos['hasta'] = date('30-11-' . $this->year);
            break;
      }

      $this->da_impuestos["ventas_totales"] = $this->saldo_cuenta('7%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["gastos_totales"] = -1 * $this->saldo_cuenta('6%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["resultado"] = $this->da_impuestos["ventas_totales"] + $this->da_impuestos["gastos_totales"];

      if($this->da_impuestos["resultado"] < 0)
      {
         $this->da_impuestos["sociedades"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->year]['sociedades'];
         $this->da_impuestos["sociedades"] = -1 * $this->da_impuestos["resultado"] * $sociedades / 100;
      }

      $this->da_impuestos["pago-ant"] = $this->saldo_cuenta('473%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["pagofraccionado-mod202"] = $this->da_impuestos["sociedades"] + $this->da_impuestos["pago-ant"];

      /// Ahora comparamos con los datos del año anterior
      $this->da_impuestos['desde'] = date('1-1-' . ($this->year - 1) );
      $this->da_impuestos['hasta'] = date('31-12-' . ($this->year - 1) );

      if(!empty($this->config[$this->year - 1]['regularizacion']['numero']))
      {
         $this->da_impuestos["resultado_ejanterior"] = $this->saldo_cuenta_asiento_regularizacion('129%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"], $this->config[$this->year - 1]['regularizacion']['numero']);
      }

      $this->da_impuestos["resultado_negotros"] = -1 * $this->saldo_cuenta('121%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["total"] = $this->da_impuestos["resultado_ejanterior"] + $this->da_impuestos["resultado_negotros"];

      if($this->da_impuestos["total"] < 0)
      {
         $this->da_impuestos["sociedades_ant"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->year - 1]['sociedades'];
         $this->da_impuestos["sociedades_ant"] = $this->da_impuestos["total"] * $sociedades / 100;
      }

      $this->da_impuestos['desde'] = date('1-1-' . $this->year);
      $this->da_impuestos['hasta'] = date('31-12-' . $this->year);
      $this->da_impuestos["sociedades_adelantos"] = -1 * $this->saldo_cuenta('4709%', $this->da_impuestos["desde"], $this->da_impuestos["hasta"]);
      $this->da_impuestos["total-mod200"] = $this->da_impuestos["sociedades_ant"] + $this->da_impuestos["sociedades_adelantos"];

      /// Definimos estructura para resultadosituacion		
      $this->da_resultadosituacion = array(
          'total' => 0,
      );

      $this->da_resultadosituacion["total"] = $this->da_tesoreria["total_tesoreria"] + $this->da_gastoscobros["total_gastoscobros"] +
              $this->da_impuestos["irpf-mod111"] + $this->da_impuestos["irpf-mod115"] + $this->da_impuestos["resultado_iva-mod303"] +
              $this->da_impuestos["pagofraccionado-mod202"] + $this->da_impuestos["total-mod200"];
   }

   private function saldo_cuenta($cuenta, $desde, $hasta)
   {
      $saldo = 0;
      
      if($this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP')
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja 
         $sql = "select sum(haber-debe) as total from co_partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
                 . " in (select idasiento from co_asientos where fecha >= " . $this->empresa->var2str($desde)
                 . " and fecha <= " . $this->empresa->var2str($hasta) . ");";

         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
         }
      }
      
      return $saldo;
   }

   private function saldo_cuenta_asiento_regularizacion($cuenta, $desde, $hasta, $numasientoregularizacion)
   {
      $saldo = 0;

      if($this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP')
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja 
         $sql = "select sum(haber-debe) as total from co_partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
                 . " in (select idasiento from co_asientos where fecha >= " . $this->empresa->var2str($desde)
                 . " and fecha <= " . $this->empresa->var2str($hasta) . " and numero = " . $numasientoregularizacion . ");";

         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
         }
      }
      
      return $saldo;
   }
   
   private function get_bancos()
   {
      $this->bancos = array();
      
      $sql = "SELECT * FROM co_subcuentas WHERE codcuenta = '572' AND codejercicio = "
              .$this->empresa->var2str($this->year).";";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $this->bancos[] = new subcuenta($d);
         }
      }
   }
   
   private function get_cajas()
   {
      $this->cajas = array();
      
      $sc0 = new subcuenta();
      foreach($sc0->all_from_cuentaesp('CAJA', $this->year) as $sc)
      {
         $this->cajas[] = $sc;
      }
   }
}
