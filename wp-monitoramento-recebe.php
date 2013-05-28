<?php /* -*- Mode: php; c-basic-offset:4; -*- */
/* Copyright (C) 2013  Leo andrade <leo-andrade@procergs.rs.gov.br>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* This module holds all functions that exposes post attributes and post
 * related information. This is an attempt to improve the modularization
 * of this plugin.
 */
header('Content-Type:text/html; charset=utf-8');
include '../../../wp-load.php';

class gdMonitore {
	
	var $userwp			= '5';
	var $statusPost		= 'pending';
	var $postType	    = 'gdobra';
	var $gdobras_prefix = 'gdobra_';
	var $porc_concluido = 'porc_concluido';
	var $ini_efetivo    = 'inicio_efetivo';
	var $fim_previsto   = 'fim_previsto';
	var $valor_global   = 'valor_global';
	var $emp_contratada = 'empresa_contratada';
	var $objetivo       = 'objetivo_estrategico';
	var $projeto        = 'projeto';
	var $stream		    = 'stream';
	var $coordenadas    = 'coordenadas';
	var $municipio      = 'municipio';
	var $numcodigopk    = 'numcodigopk';
	

    public function gdMonitoreChamada($url)
    {
   		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$json = curl_exec($curl);
		curl_close($curl);
		$encoded = json_decode($json);

		return $encoded;
    }
	
	public function gdMonitoreMontaUrl(){

		$timestmp  = time();
		$apiKey     = 'myPublicKey';
		$PrivateKey = '3248cafb4a54264cf3d139ca35976d51104b7808c41b8f7a1dcc3cb68a946820';
		$parturl    = '/sme/obras/list.json?';
		$url 		= "http://smegov.des.procergs.reders";
		
		$call = 'GET '.$parturl."apiKey=".$apiKey."&timestamp=".$timestmp;
		$Signature = hash_hmac('sha256', $call, $PrivateKey);
		$url = $url.$parturl."apiKey=".$apiKey."&signature=".$Signature."&timestamp=".$timestmp;

		return $url;
	}

	function gdMonitoreInsert($my_post){
		$post_id = wp_Insert_post( $my_post );
		return $post_id;
	}

	function gdMonitoreInsertCustomField($post_id, $myvalues){
		foreach ($myvalues as $key => $value) {
			add_post_meta($post_id, $key, $value);
		}
	}
	
	function gdMonitoreInsertEvidences($post_id, $evidences){
		foreach ($evidences as $a) {
			$my_post = $this->gdMonitoreMontaPost(NULL, $a->str_titulo, $a->url, $a->dat_documento, $post_id);
			$post_id_inc = $this->gdMonitoreInsert($my_post);
			if ($post_id_inc) {
				$content_type = wp_get_http_headers($a->url);
				$content_type = $content_type['content-type'];
				
				if ($content_type == "image/jpeg" or $content_type == "image/png" or $content_type == "image/gif")
					set_post_format($post_id_inc, 'image' );
				else
					set_post_format($post_id_inc, 'Link' );
				
				$myvalues = array(
					$this->gdobras_prefix.$this->numcodigopk 	=> $a->num_codigo_pk
					);

				$this->gdMonitoreInsertCustomField($post_id_inc, $myvalues);
			}
			
		}
	}
	
	function gdMonitoreInsertSituation($post_id, $situation){
		foreach ($situation as $a) {
			$my_post = $this->gdMonitoreMontaPost(NULL, $a->num_codigo_pk, $a->str_situacao, $a->dat_alteracao, $post_id);
			$this->gdMonitoreInsert($my_post);
		}	
	}
		
	function gdMonitoreupdate($my_post){
		$post_id =   wp_update_post( $my_post );
		return $post_id;
	}

	function gdMonitoreVerificaObra($post_id){
		$sCodigoPk = $this->gdobras_prefix.$this->numcodigopk;
		
		$args = array( 	'post_type' => 'gdobra' 
					  , 'post_status' => 'any'
					  , 'meta_key'        => $sCodigoPk
					  , 'meta_value'      => $post_id
					); 
		
		$valor = get_posts($args);
		if (!empty($valor)) 
			$valor = $valor[0]->ID;
		
		return $valor;
	} 


	function gdMonitoreMontaPost($post_id, $title, $content, $post_date, $post_parent){
		$my_post = array(
			'ID'			 => $post_id,
			'post_title'     => $title,
			'post_content'   => $content,
			'post_status'    => $this->statusPost,
			'post_type'	     => $this->postType,
			'post_author'    => $this->userwp,
			'post_parent'    => $post_parent,
			'post_date'	     => $post_date,
			'post_date_gmt'  => date("Y-m-d H:i:s"),
			'comment_status' => 'open'
			);
		return $my_post;
	}

	
	function gdMonitoreMontaPostUpdate($post_id, $titulo, $descricao){
		$my_post = array(
			'ID'             => $post_id,	
			'post_title'     => $titulo,
			'post_content'   => $descricao,
			'post_author'    => $userwp,
			'post_date'	     => date("Y-m-d H:i:s"),
			'post_date_gmt'  => date("Y-m-d H:i:s"),
			
			);
		return $my_post;
	}

	function gdMonitoreMontaCamposCustom($porcExec,$dtinicio,$dtfim,$valor,$empresa,$objetivo,$projeto,$urlstream,$municipio,$sCodigoPk){

		 
		$objetivo = !empty($objetivo->title) ? $objetivo->title : '';
		$projeto  = !empty($projeto->title)  ? $projeto->title  : '';
		if (!empty($urlstream)) {
			foreach ($urlstream as $a) {
				if ($a->name == "URL stream") {
					$urlstream = $a->value;
					break;
				}
			}
		}
		if (!empty($municipio)) {
			$cont = 0;
			foreach ($municipio as $a) {
				$mun[$cont] = $a->str_nome;
				$coo[$cont] = $a->num_latitude.",".$a->num_longitude;
				$cont++; 
			}
		} else {
			$mun = '';
			$coo = '';
		}
	
		$my_post = array(
			$this->gdobras_prefix.$this->porc_concluido => $porcExec,
			$this->gdobras_prefix.$this->ini_efetivo 	=> $dtinicio,
			$this->gdobras_prefix.$this->fim_previsto 	=> $dtfim,
			$this->gdobras_prefix.$this->valor_global 	=> $valor,
			$this->gdobras_prefix.$this->emp_contratada	=> $empresa,
			$this->gdobras_prefix.$this->objetivo 		=> $objetivo,
			$this->gdobras_prefix.$this->projeto 		=> $projeto,
			$this->gdobras_prefix.$this->stream 		=> $urlstream,
			$this->gdobras_prefix.$this->coordenadas 	=> $coo,
			$this->gdobras_prefix.$this->municipio 		=> $mun,
			$this->gdobras_prefix.$this->numcodigopk 	=> $sCodigoPk
			);

		return $my_post;
	}
}

$rsClasse = new gdMonitore();
try{
	$url = $rsClasse->gdMonitoreMontaUrl();
	$rs = $rsClasse->gdMonitoreChamada($url);
} catch (Exception $e){
	echo $e->getMessage();
	
}

foreach ($rs as $c){

	if ($c->num_codigo_pk == 641) {
		//Verifica se já existe o post da obra
		$val = $rsClasse->gdMonitoreVerificaObra($c->num_codigo_pk);
		if ($val) {
			echo "Update<br>";
		} else {
			$my_post = $rsClasse->gdMonitoreMontaPost(NULL,$c->str_titulo_obra, $c->str_descricao_obra, date('Y-m-d H:i:s'),NULL);
			$post_id = $rsClasse->gdMonitoreInsert($my_post);
			if ($post_id){
				$myvalues = $rsClasse->gdMonitoreMontaCamposCustom($c->num_percentual_execucao, 
																 $c->dat_inicio_real, 
																 $c->dat_termino_prevista, 
																 $c->num_val_global, 
																 $c->str_nome_empresa_contratada, 
																 $c->objective, 
																 $c->project, 
																 $c->customFields,
																 $c->cities,
																 $c->num_codigo_pk);
				
				$rsClasse->gdMonitoreInsertCustomField($post_id, $myvalues);
				$rsClasse->gdMonitoreInsertSituation($post_id, $c->publicSituation);
				$rsClasse->gdMonitoreInsertEvidences($post_id, $c->evidences);
				
			}
			echo "<br>Inserido com Sucesso<br>";
		}
		
	}
	
} 

?>