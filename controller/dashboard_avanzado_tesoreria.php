<?php

/*
 * This file is part of dashboard_avanzado
 * Copyright (C) 2017   Itaca Software Libre contacta@itacaswl.com
 * Copyright (C) 2017   Carlos Garcia Gomez   neorazorx@gmail.com
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
 * @author Carlos García Gómez
 * @author juanguinho - Itaca Software Libre
 */
class dashboard_avanzado_tesoreria extends fs_controller
{
   public $bancos;
   public $cajas;
   public $codejercicio;
   public $config;
   public $da_gastoscobros;
   public $da_impuestos;
   public $da_reservasresultados;
   public $da_resultadoejercicioactual;
   public $da_resultadosituacion;
   public $da_tesoreria;
   public $desde;
   public $hasta;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard Avanzado', 'admin', FALSE, FALSE);
   }

   protected function private_core()
   {
      $this->codejercicio = date('Y');
      $this->desde = date('01-01-Y');
      $this->hasta = date('31-12-Y');
      
      /// seleccionamos el ejercicio actual
      $ejercicio = new ejercicio();
      foreach($ejercicio->all() as $eje)
      {
         if( date('Y', strtotime($eje->fechafin)) == date('Y') )
         {
            $this->codejercicio = $eje->codejercicio;
            $this->desde = $eje->fechainicio;
            $this->hasta = $eje->fechafin;
            break;
         }
      }
      
      $fsvar = new fs_var();
      $this->config = json_decode($fsvar->simple_get('dashboard_avanzado_config'), true);
      
      $this->cuadro_tesoreria();
      $this->cuadro_gastos_y_cobros();
      $this->cuadro_reservas();
      $this->cuadro_resultado_actual();
      $this->cuadro_impuestos();
      $this->cuadro_resultados_situacion_corto();
   }
   
   private function cuadro_tesoreria()
   {
      /**
       * Cuadro de tesorería.
       */
      $this->da_tesoreria = array(
          'total_cajas' => 0,
          'total_bancos' => 0,
          'total_tesoreria' => 0,
      );
      $this->get_bancos();
      foreach($this->bancos as $banco)
      {
         $this->da_tesoreria["total_bancos"] += $banco->saldo;
      }
      
      $this->get_cajas();
      foreach($this->cajas as $caja)
      {
         $this->da_tesoreria["total_cajas"] += $caja->saldo;
      }
      
      $this->da_tesoreria["total_tesoreria"] = $this->da_tesoreria["total_cajas"] + $this->da_tesoreria["total_bancos"];
   }
   
   private function cuadro_gastos_y_cobros()
   {
      /**
       * Cuadro gastos y cobros.
       */
      $this->da_gastoscobros = array(
          'gastospdtepago' => -1 * $this->get_gastos_pendientes(),
          'clientespdtecobro' => $this->get_cobros_pendientes(),
          'nominaspdtepago' => $this->saldo_cuenta('465%', $this->desde, $this->hasta),
          'segsocialpdtepago' => $this->saldo_cuenta('476%', $this->desde, $this->hasta),
          'segsocialpdtecobro' => $this->saldo_cuenta('471%', $this->desde, $this->hasta),
          'total_gastoscobros' => 0,
      );
      
      $this->da_gastoscobros["total_gastoscobros"] = $this->da_gastoscobros["gastospdtepago"]
              + $this->da_gastoscobros["clientespdtecobro"] + $this->da_gastoscobros["nominaspdtepago"]
              + $this->da_gastoscobros["segsocialpdtepago"] + $this->da_gastoscobros["segsocialpdtecobro"];
   }
   
   private function cuadro_impuestos()
   {
      /**
       * Cuadro de impuestos.
       */
      $this->da_impuestos = array(
          'irpf-mod111' => $this->saldo_cuenta('4751%', $this->desde, $this->hasta),
          'irpf-mod115' => 0,
          'iva-repercutido' => $this->saldo_cuenta('477%', $this->desde, $this->hasta),
          'iva-soportado' => $this->saldo_cuenta('472%', $this->desde, $this->hasta),
          'iva-devolver' => $this->saldo_cuenta('4700%', $this->desde, $this->hasta),
          'resultado_iva-mod303' => 0,
          'ventas_totales' => $this->get_ventas_totales(),
          'gastos_totales' => -1 * $this->saldo_cuenta('600%', $this->desde, $this->hasta),
          'resultado' => 0,
          'sociedades' => 0,
          'pago-ant' => $this->saldo_cuenta('473%', $this->desde, $this->hasta),
          'pagofraccionado-mod202' => 0,
          'resultado_ejanterior' => -1 * $this->saldo_cuenta('129%', $this->desde, $this->hasta),
          'resultado_negotros' => -1 * $this->saldo_cuenta('121%', $this->desde, $this->hasta),
          'total' => 0,
          'sociedades_ant' => 0,
          'sociedades_adelantos' => -1 * $this->saldo_cuenta('4709%', $this->desde, $this->hasta),
          'total-mod200' => 0,
      );
      
      // cogemos las cuentas del alquiler de la configuración para generar el mod-115
      if( isset($this->config[$this->codejercicio]['irpfalquiler']) )
      {
         $cuentasalquiler = explode(",", $this->config[$this->codejercicio]['irpfalquiler']);
         foreach($cuentasalquiler as $cuentaalquiler)
         {
            if($cuentaalquiler)
            {
               $this->da_impuestos["irpf-mod115"] += $this->saldo_cuenta($cuentaalquiler, $this->desde, $this->hasta);
               $this->da_impuestos["irpf-mod111"] -= $this->saldo_cuenta($cuentaalquiler, $this->desde, $this->hasta);
            }
         }
      }
      
      $this->da_impuestos["resultado_iva-mod303"] = $this->da_impuestos["iva-repercutido"] + $this->da_impuestos["iva-soportado"]
              + $this->da_impuestos["iva-devolver"];

      $this->da_impuestos["resultado"] = $this->da_impuestos["ventas_totales"] + $this->da_impuestos["gastos_totales"];

      if($this->da_impuestos["resultado"] < 0)
      {
         $this->da_impuestos["sociedades"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->codejercicio]['sociedades'];
         $this->da_impuestos["sociedades"] = -1 * $this->da_impuestos["resultado"] * $sociedades / 100;
      }

      $this->da_impuestos["pagofraccionado-mod202"] = $this->da_impuestos["sociedades"] + $this->da_impuestos["pago-ant"];

      $this->da_impuestos["total"] = $this->da_impuestos["resultado_ejanterior"] + $this->da_impuestos["resultado_negotros"];

      if($this->da_impuestos["total"] < 0)
      {
         $this->da_impuestos["sociedades_ant"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->codejercicio - 1]['sociedades'];
         $this->da_impuestos["sociedades_ant"] = $this->da_impuestos["total"] * $sociedades / 100;
      }

      $this->da_impuestos["total-mod200"] = $this->da_impuestos["sociedades_ant"] - $this->da_impuestos["sociedades_adelantos"];
   }
   
   private function cuadro_resultados_situacion_corto()
   {
      $this->da_resultadosituacion["total"] = $this->da_tesoreria["total_tesoreria"] + $this->da_gastoscobros["total_gastoscobros"] +
              $this->da_impuestos["irpf-mod111"] + $this->da_impuestos["irpf-mod115"] + $this->da_impuestos["resultado_iva-mod303"] +
              $this->da_impuestos["pagofraccionado-mod202"] + $this->da_impuestos["total-mod200"];
   }
   
   private function cuadro_reservas()
   {
      /**
       * Cuadro reservas + resultados
       */
      $this->da_reservasresultados = array(
          'reservalegal' => -1 * $this->saldo_cuenta('112%', $this->desde, $this->hasta),
          'reservasvoluntarias' => -1 * $this->saldo_cuenta('113%', $this->desde, $this->hasta),
          'resultadoejercicioanterior' => abs( $this->saldo_cuenta('129%', $this->desde, $this->hasta) ) - $this->saldo_cuenta('121%', $this->desde, $this->hasta),
          'total_reservas' => 0,
      );
      
      $this->da_reservasresultados["total_reservas"] = $this->da_reservasresultados["reservalegal"]
              + $this->da_reservasresultados["reservasvoluntarias"] + $this->da_reservasresultados["resultadoejercicioanterior"];
   }
   
   private function cuadro_resultado_actual()
   {
      /**
       * Cuadro resultados ejercicio actual
       */
      $this->da_resultadoejercicioactual = array(
          'total_ventas' => $this->get_ventas_totales(),
          'total_gastos' => -1 * $this->get_compras_totales(),
          'resultadoexplotacion' => 0,
          'amortizacioninmovintang' => $this->saldo_cuenta('680%', $this->desde, $this->hasta),
          'amortizacioninmovmat' => $this->saldo_cuenta('681%', $this->desde, $this->hasta),
          'total_amort' => 0,
          'resultado_antes_impuestos' => 0,
          'impuesto_sociedades' => 0,
          'resultado_despues_impuestos' => 0,
      );

      $this->da_resultadoejercicioactual["resultadoexplotacion"] = $this->da_resultadoejercicioactual["total_ventas"]
              + $this->da_resultadoejercicioactual["total_gastos"];
      $this->da_resultadoejercicioactual["total_amort"] = $this->da_resultadoejercicioactual["amortizacioninmovintang"]
              + $this->da_resultadoejercicioactual["amortizacioninmovmat"];
      $this->da_resultadoejercicioactual["resultado_antes_impuestos"] = $this->da_resultadoejercicioactual["resultadoexplotacion"]
              + $this->da_resultadoejercicioactual["total_amort"];

      if($this->da_resultadoejercicioactual["resultado_antes_impuestos"] < 0)
      {
         $this->da_resultadoejercicioactual["impuesto_sociedades"] = 0;
      }
      else
      {
         $sociedades = $this->config[$this->codejercicio]['sociedades'];
         $this->da_resultadoejercicioactual["impuesto_sociedades"] = -1 * $this->da_resultadoejercicioactual["resultado_antes_impuestos"]
                 * $sociedades / 100;
      }

      $this->da_resultadoejercicioactual["resultado_despues_impuestos"] = $this->da_resultadoejercicioactual["resultado_antes_impuestos"]
              + $this->da_resultadoejercicioactual["impuesto_sociedades"];
   }
   
   private function saldo_cuenta($cuenta, $desde, $hasta)
   {
      $saldo = 0;
      
      if( $this->db->table_exists('co_partidas') )
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja 
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
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

      if( $this->db->table_exists('co_partidas') )
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja 
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
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
              .$this->empresa->var2str($this->codejercicio).";";
      
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
      foreach($sc0->all_from_cuentaesp('CAJA', $this->codejercicio) as $sc)
      {
         $this->cajas[] = $sc;
      }
   }
   
   private function get_gastos_pendientes()
   {
      $total = 0;
      
      $sql = "SELECT SUM(total) as total FROM facturasprov WHERE pagada = false;";
      $data = $this->db->select($sql);
      if($data)
      {
         $total = floatval($data[0]['total']);
      }
      
      return $total;
   }
   
   private function get_cobros_pendientes()
   {
      $total = 0;
      
      $sql = "SELECT SUM(total) as total FROM facturascli WHERE pagada = false;";
      $data = $this->db->select($sql);
      if($data)
      {
         $total = floatval($data[0]['total']);
      }
      
      return $total;
   }
   
   private function get_ventas_totales()
   {
      $total = 0;
      
      $sql = "SELECT SUM(neto) as total FROM facturascli WHERE fecha >= ".$this->empresa->var2str($this->desde)
              ." AND fecha <= ".$this->empresa->var2str($this->hasta).';';
      $data = $this->db->select($sql);
      if($data)
      {
         $total = floatval($data[0]['total']);
      }
      
      return $total;
   }
   
   private function get_compras_totales()
   {
      $total = 0;
      
      $sql = "SELECT SUM(neto) as total FROM facturasprov WHERE fecha >= ".$this->empresa->var2str($this->desde)
              ." AND fecha <= ".$this->empresa->var2str($this->hasta).';';
      $data = $this->db->select($sql);
      if($data)
      {
         $total = floatval($data[0]['total']);
      }
      
      return $total;
   }
}
