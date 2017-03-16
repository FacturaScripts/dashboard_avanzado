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

require_model('ejercicio.php');
require_model('partida.php');
require_model('asiento.php');


/**
 * Configuración de las opciones de Gestión Documental
 *
 * @author Angel Albiach
 */
class dashboard_avanzado_config extends fs_controller
{

    public $ejercicios;
    public $config;


    public function __construct()
    {
        parent::__construct(__CLASS__, '', '', FALSE, TRUE, FALSE);
    }

    protected function private_core()
    {
        $ejer             = new ejercicio();
        $this->ejercicios = $ejer->all();

        $fsvar = new fs_var();

        if (isset($_POST['save']))
        {
            $config = $_POST['config'];

            foreach ($config as $codejercicio => $asiento)
            {
                $num_regularizacion      = $asiento['regularizacion']['numero'];
//                $concepto_regularizacion = $this->get_asiento($codejercicio, $num_regularizacion)[0]['concepto'];
                $asiento=$this->get_asiento($codejercicio, $num_regularizacion);
                $concepto_regularizacion = $asiento[0]['concepto'];
                $config[$codejercicio]['regularizacion']['numero']   = $num_regularizacion;
                $config[$codejercicio]['regularizacion']['concepto'] = ($concepto_regularizacion) ? $concepto_regularizacion : 'ERROR: El número de asiento es incorrecto';
                $config[$codejercicio]['regularizacion']['error'] = ($concepto_regularizacion) ? 0 : 1;
                              
            }
            
            $json = json_encode($config);
            $fsvar->simple_save('dashboard_avanzado_config', $json);
            $this->config = $config;
        } else {
        	
        	   
            $this->config = json_decode($fsvar->simple_get('dashboard_avanzado_config'), true);
            
        }

    }

    protected function get_asiento($codejercicio, $numero)
    {
        $ejer = new ejercicio();

        if (isset($codejercicio) && isset($numero))
        {
            $data = $this->db->select("SELECT * FROM co_asientos WHERE codejercicio = " . $ejer->var2str($codejercicio) . " AND numero = " . $ejer->var2str($numero) . ";");
            if ($data)
            {
                return $data;
            } else
                return array();
        }
    }

}
