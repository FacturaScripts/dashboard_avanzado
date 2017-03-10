<?php

/*
 * This file is part of dashboard_avanzado
 * Copyright (C) 2016-2017 Fusió d'Arts          contacto@fusiodarts.com
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

require_model('articulo.php');
require_model('cuenta.php');
require_model('ejercicio.php');
require_model('familia.php');
require_model('subcuenta.php');

class dashboard_avanzado extends fs_controller
{
   public $charts;
   public $config;
   public $gastos;
   public $ejercicios;
   public $lastyear;
   public $number;
   public $porc;
   public $resultado;
   public $ventas;
   public $year;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard Avanzado', 'admin');
   }

   protected function private_core()
   {
      $this->gastos = '';
      $this->number = '<span style="color:#ccc;">0,00 €</span>';
      $this->porc = '<span style="color:#ccc;">0 %</span>';
      $this->resultado = '';
      $this->ventas = '';
      
      $ejer = new ejercicio();
      $this->ejercicios = $ejer->all();
      
      $fsvar = new fs_var();
      $this->config = json_decode($fsvar->simple_get('dashboard_avanzado_config'), true);

      // Obtenemos el año a filtrar, sino es el actual
      if(isset($_POST['year']) && $_POST['year'] != '')
      {
         $year = $_POST['year'];
      }
      else
      {
         $year = date('Y');
      }
      $this->year = $year;
      $this->lastyear = $year - 1;

      // Llamamos a la función que crea los arrays con los datos,
      // pasandole este año y el anterior
      $this->build_year($this->year);
      $this->build_year($this->lastyear);

      /**
       * CHARTS
       * *****************************************************************
       */
      for($mes = 1; $mes <= 12; $mes++)
      {
         if( isset($this->charts['totales']['ventas'][$mes]) )
         {
            $this->charts['totales']['ventas'][$mes] = ($this->ventas[$this->year]['total_mes'][$mes]) ? $this->ventas[$this->year]['total_mes'][$mes] : 0;
            $this->charts['totales']['gastos'][$mes] = ($this->gastos[$this->year]['total_mes'][$mes]) ? $this->gastos[$this->year]['total_mes'][$mes] : 0;
            $this->charts['totales']['resultado'][$mes] = ($this->resultado[$this->year]['total_mes'][$mes]) ? $this->resultado[$this->year]['total_mes'][$mes] : 0;
         }
         else
         {
            $this->charts['totales']['ventas'][$mes] = 0;
            $this->charts['totales']['gastos'][$mes] = 0;
            $this->charts['totales']['resultado'][$mes] = 0;
         }
      }

      $i = 1;
      $count = count($this->ventas[$this->year]['porc_fam']);
      $colores = $labels = $porcentajes = '';
      foreach($this->ventas[$this->year]['porc_fam'] as $codfamilia => $porc)
      {
         $sep = ($count == $i) ? '' : ',';

         if($codfamilia == 'SIN_FAMILIA')
         {
            $fam_desc = 'Sin Familia';
         }
         else
         {
            $fam = new familia();
            $familia = $fam->get($codfamilia);
            $fam_desc = $familia->descripcion;
         }

         $labels .= '"' . $fam_desc . '"' . $sep;
         $porcentajes .= $porc . $sep;
         $colores .= '"#' . $this->randomColor() . '"' . $sep;

         ++$i;
      }
      $this->charts['distribucion']['labels'] = '['.$labels.']';
      $this->charts['distribucion']['porc'] = '['.$porcentajes.']';
      $this->charts['distribucion']['colors'] = '['.$colores.']';
   }

   protected function build_year($year)
   {
      $date = array(
          'desde' => '',
          'hasta' => '',
      );
      $ventas = array(
          'familias' => array(),
          'total_fam' => array(),
          'total_fam_mes' => array(),
          'total_ref' => array(),
          'total_mes' => array(),
          'porc_fam' => array(),
          'porc_ref' => array(),
      );
      $gastos = array(
          'cuentas' => array(),
          'total_cuenta' => array(),
          'total_cuenta_mes' => array(),
          'total_subcuenta' => array(),
          'total_mes' => array(),
          'porc_cuenta' => array(),
          'porc_subcuenta' => array(),
      );
      $resultado = array(
          'total_mes' => array(),
      );
      $this->charts = array(
          'totales' => array(),
          'distribucion' => array(),
      );
      $ventas_total_meses = '';
      $gastos_total_meses = '';

      $asiento_regularizacion = $this->config[$year]['regularizacion']['numero'];

      // Recorremos los meses y ejecutamos una consulta filtrando por el mes
      for($mes = 1; $mes <= 12; $mes++)
      {
         /// inicializamos
         $ventas['total_mes'][$mes] = 0;
         $gastos['total_mes'][$mes] = 0;
         
         $dia_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $year);

         $date['desde'] = date('1-' . $mes . '-' . $year);
         $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

         /**
          *  VENTAS
          * *****************************************************************
          */
         // VENTAS: Consulta con las lineasfacturascli
         $sql = "select lfc.referencia, sum(lfc.pvptotal) as pvptotal from lineasfacturascli as lfc"
                 . " LEFT JOIN facturascli as fc ON lfc.idfactura = fc.idfactura"
                 . " where fc.fecha >= " . $this->empresa->var2str($date['desde'])
                 . " AND fc.fecha <= " . $this->empresa->var2str($date['hasta'])
                 . " group by lfc.referencia";
         
         // VENTAS: Recorremos lineasfacturascli y montamos arrays
         $lineas = $this->db->select($sql);
         if($lineas)
         {
            foreach($lineas as $dl)
            {
               $data = $this->build_data($dl);
               $pvptotal = $data['pvptotal'];
               $referencia = $data['ref'];
               $codfamilia = $data['codfamilia'];
               $familia = $data['familia'];
               
               // Arrays con los datos a mostrar
               if( isset($ventas['total_fam_mes'][$codfamilia][$mes]) )
               {
                  $ventas['total_fam_mes'][$codfamilia][$mes] += $pvptotal;
               }
               else
               {
                  $ventas['total_fam_mes'][$codfamilia][$mes] = $pvptotal;
               }
               
               if( isset($ventas['total_fam'][$codfamilia]) )
               {
                  $ventas['total_fam'][$codfamilia] += $pvptotal;
               }
               else
               {
                  $ventas['total_fam'][$codfamilia] = $pvptotal;
               }
               
               if( isset($ventas['total_ref'][$codfamilia][$referencia]) )
               {
                  $ventas['total_ref'][$codfamilia][$referencia] += $pvptotal;
               }
               else
               {
                  $ventas['total_ref'][$codfamilia][$referencia] = $pvptotal;
               }
               
               $ventas['total_mes'][$mes] = $pvptotal + $ventas['total_mes'][$mes];
               $ventas_total_meses = $pvptotal + $ventas_total_meses;
               
               // Array temporal con los totales (falta añadir descripción familia)
               $ventas['familias'][$codfamilia][$referencia][$mes] = array('pvptotal' => $pvptotal);
            }
         }

         // Las descripciones solo las necesitamos en el año seleccionado,
         // en el año anterior se omite
         if($year == $this->year)
         {
            // Recorremos ventas['familias'] crear un array con las descripciones de las familias y artículos
            foreach($ventas['familias'] as $codfamilia => $familia)
            {
               foreach($familia as $referencia => $array)
               {
                  $dl['referencia'] = $referencia;
                  $data = $this->build_data($dl);

                  $ventas['descripciones'][$codfamilia] = $data['familia'];
                  $ventas['descripciones'][$referencia] = $data['art_desc'];
               }
            }
         }
         
         if( $this->db->table_exists('co_partidas') )
         {
            /**
             *  GASTOS
             * *****************************************************************
             */
            // Gastos: Consulta de las partidas y asientos del grupo 6
            $sql = "select * from co_partidas as par"
                    . " LEFT JOIN co_asientos as asi ON par.idasiento = asi.idasiento"
                    . " where asi.fecha >= " . $this->empresa->var2str($date['desde'])
                    . " AND asi.fecha <= " . $this->empresa->var2str($date['hasta'])
                    . " AND codsubcuenta LIKE '6%'";
            
            if($asiento_regularizacion)
            {
               $sql .= " AND asi.numero <> " . $this->empresa->var2str($asiento_regularizacion);
            }
            
            $sql .= " ORDER BY codsubcuenta";
            
            $partidas = $this->db->select($sql);
            if($partidas)
            {
               foreach($partidas as $p)
               {
                  $codcuenta = substr($p['codsubcuenta'], 0, 3);
                  $codsubcuenta = $p['codsubcuenta'];
                  $pvptotal = $p['debe'] - $p['haber'];
                  
                  // Array con los datos a mostrar
                  if( isset($gastos['total_cuenta_mes'][$codcuenta][$mes]) )
                  {
                     $gastos['total_cuenta_mes'][$codcuenta][$mes] += $pvptotal;
                  }
                  else
                  {
                     $gastos['total_cuenta_mes'][$codcuenta][$mes] = $pvptotal;
                  }
                  
                  if( isset($gastos['total_cuenta'][$codcuenta]) )
                  {
                     $gastos['total_cuenta'][$codcuenta] += $pvptotal;
                  }
                  else
                  {
                     $gastos['total_cuenta'][$codcuenta] = $pvptotal;
                  }
                  
                  if( isset($gastos['total_subcuenta'][$codcuenta][$codsubcuenta]) )
                  {
                     $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] += $pvptotal;
                  }
                  else
                  {
                     $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] = $pvptotal;
                  }
                  
                  if( isset($gastos['total_mes'][$mes]) )
                  {
                     $gastos['total_mes'][$mes] += $pvptotal;
                  }
                  else
                  {
                     $gastos['total_mes'][$mes] = $pvptotal;
                  }
                  
                  $gastos_total_meses = $pvptotal + $gastos_total_meses;
                  
                  if( isset($gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]) )
                  {
                     $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes] = array('pvptotal' => $pvptotal + $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]['pvptotal']);
                  }
                  else
                  {
                     $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes] = array('pvptotal' => $pvptotal);
                  }
               }
            }
         }

         // Las descripciones solo las necesitamos en el año seleccionado,
         // en el año anterior se omite
         if($year == $this->year)
         {
            // GASTOS: Creamos un array con las descripciones de las cuentas y subcuentas
            foreach($gastos['cuentas'] as $codcuenta => $arraycuenta)
            {
               $c = new cuenta();
               $cuenta = $c->get_by_codigo($codcuenta, $year);

               foreach($arraycuenta as $codsubcuenta => $arraysubcuenta)
               {
                  $s = new subcuenta();
                  $subcuenta = $s->get_by_codigo($codsubcuenta, $year);
                  $gastos['descripciones'][$codcuenta] = $cuenta->descripcion;
                  $gastos['descripciones'][$codsubcuenta] = $subcuenta->descripcion;
               }
            }
         }
         /**
          *  RESULTADOS
          * *****************************************************************
          */
         if( isset($ventas['total_mes'][$mes]) )
         {
            $resultado['total_mes'][$mes] = bround($ventas['total_mes'][$mes] - $gastos['total_mes'][$mes], FS_NF0_ART);
         }
         else
         {
            $resultado['total_mes'][$mes] = 0;
         }
      }

      /**
       *  TOTALES GLOBALES
       * *****************************************************************
       */
      $ventas['total_mes'][0] = bround($ventas_total_meses, FS_NF0_ART);
      $ventas['total_mes']['media'] = bround($ventas_total_meses / 12, FS_NF0_ART);
      $gastos['total_mes'][0] = bround($gastos_total_meses, FS_NF0_ART);
      $gastos['total_mes']['media'] = bround($gastos_total_meses / 12, FS_NF0_ART);
      $resultado['total_mes'][0] = bround($ventas_total_meses - $gastos_total_meses, FS_NF0_ART);
      $resultado['total_mes']['media'] = bround(($ventas_total_meses - $gastos_total_meses) / 12, FS_NF0_ART);

      /**
       *  PORCENTAJES
       * *****************************************************************
       */
      // VENTAS: Calculamos los porcentajes con los totales globales
      foreach($ventas['familias'] as $codfamilia => $familias)
      {
         $ventas['porc_fam'][$codfamilia] = bround($ventas['total_fam'][$codfamilia] * 100 / $ventas_total_meses, FS_NF0_ART);
         foreach($familias as $referencia => $array)
         {

            $ventas['porc_ref'][$codfamilia][$referencia] = bround($ventas['total_ref'][$codfamilia][$referencia] * 100 / $ventas_total_meses, FS_NF0_ART);
         }
      }

      // GASTOS: Calculamos los porcentajes con los totales globales
      foreach($gastos['cuentas'] as $codcuenta => $cuenta)
      {
         $gastos['porc_cuenta'][$codcuenta] = bround($gastos['total_cuenta'][$codcuenta] * 100 / $gastos_total_meses, FS_NF0_ART);
         foreach($cuenta as $codsubcuenta => $subcuenta)
         {
            $gastos['porc_subcuenta'][$codcuenta][$codsubcuenta] = bround($gastos['total_subcuenta'][$codcuenta][$codsubcuenta] * 100 / $gastos_total_meses, FS_NF0_ART);
         }
      }

      // Variables globales para usar en la vista
      $this->ventas[$year] = $ventas;
      $this->gastos[$year] = $gastos;
      $this->resultado[$year] = $resultado;

      return;
   }

   /**
    * Prepara los datos de la consulta de ventas y los guarda en un array
    * @param array $dl Array con los datos dela cada row de la consulta
    * @return array Datos de la consulta listos para usar
    */
   protected function build_data($dl)
   {
      $pvptotal = bround($dl['pvptotal'], FS_NF0_ART);
      $referencia = $dl['referencia'];
      
      $articulo = FALSE;
      if($referencia)
      {
         $art = new articulo();
         $articulo = $art->get($referencia);
         if($articulo)
         {
            $art_desc = $articulo->descripcion;
            $codfamilia = $articulo->codfamilia;
            if(empty($codfamilia))
            {
               $codfamilia = 'SIN_FAMILIA';
               $familia = 'Sin Familia';
            }
            else
            {
               $familia = $articulo->get_familia()->descripcion;
            }
         }
      }
      
      if(!$articulo)
      {
         $referencia = 'SIN_REFERENCIA';
         $art_desc = 'Artículo sin referencia';
         $codfamilia = 'SIN_FAMILIA';
         $familia = 'SIN_FAMILIA';
      }
      
      return array('ref' => $referencia, 'art_desc' => $art_desc, 'codfamilia' => $codfamilia, 'familia' => $familia, 'pvptotal' => $pvptotal);
   }

   public function randomColor()
   {
      return substr(str_shuffle('ABCDEF0123456789'), 0, 6);
   }

}

/*
 * Guardo el script aquí por si auto-ordeno el código y se desmontan estas variables
                <script>
                    var dataVentas = [{loop="$fsc->charts['totales']['ventas']"}{$value1},{/loop}];
                    var dataGastos = [{loop="$fsc->charts['totales']['gastos']"}{$value1},{/loop}];
                    var dataResultado = [{loop="$fsc->charts['totales']['resultado']"}{$value1},{/loop}];
                            
                    var distribucionLabels = {$fsc->charts['distribucion']['labels']};
                    var distribucionPorc = {$fsc->charts['distribucion']['porc']};
                    var distribucionColor = {$fsc->charts['distribucion']['colors']};
       
                </script>
 * */
