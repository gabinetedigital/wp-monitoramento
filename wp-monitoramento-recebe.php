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
set_time_limit(0);
header('Content-Type:text/html; charset=utf-8');
include '../../../wp-load.php';


class gdMonitore {

	var $userwp				= 'gd_gdobra_usuario_admin';
	var $statusPost			= 'publish';
	var $postType	    	= 'gdobra';
	var $gdobras_prefix 	= 'gdobra_';
	var $gdTituloSituation 	= 'Governo Informa ';
	var $porc_concluido 	= 'porc_concluido';
	var $ini_efetivo    	= 'inicio_efetivo';
	var $fim_previsto   	= 'fim_previsto';
	var $valor_global   	= 'valor_global';
	var $emp_contratada 	= 'empresa_contratada';
	var $objetivo       	= 'objetivo_estrategico';
	var $projeto        	= 'projeto';
	var $stream		    	= 'stream';
	var $coordenadas    	= 'coordenadas';
	var $municipio      	= 'municipio';
	var $numcodigopk    	= 'numcodigopk';
	var $video		    	= 'video';
	var $arquivo	    	= 'arquivo';
	var $imagem		    	= 'imagem';


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
		//$apiKey     = $this->gdMonitoreGetOption('gd_gdobra_apikey');
		//$PrivateKey = $this->gdMonitoreGetOption('gd_gdobra_privatekey');
		//$parturl    = $this->gdMonitoreGetOption('gd_gdobra_parturl');
		//$url 		= $this->gdMonitoreGetOption('gd_gdobra_url');
		//Alterar e colocar os dados corretos.
		$apiKey     = "VERIFICAR";
		$PrivateKey = "VERIFICAR";
		$parturl    = "VERIFICAR";
		$url 	    = "VERIFICAR";	

		$call = 'GET '.$parturl."apiKey=".$apiKey."&timestamp=".$timestmp;
		$Signature = hash_hmac('sha256', $call, $PrivateKey);
		$url = $url.$parturl."apiKey=".$apiKey."&signature=".$Signature."&timestamp=".$timestmp;

		return $url;
	}

	function gdMonitoreGetOption($option){

		return get_option($option);
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

	function gdMonitoreUpdateCustomField($post_id, $myvalues){
		foreach ($myvalues as $key => $value) {

			update_post_meta($post_id, $key, $value);

		}
	}

	function gdMonitoreInsertEvidences($post_id, $evidences){

		foreach ($evidences as $a) {

			$post_parent_id = $this->gdMonitorePostParent($post_id, $a->dat_documento);

			$my_post = $this->gdMonitoreMontaPost(NULL, $a->str_titulo, $a->str_resumo, $a->dat_documento, $post_parent_id);
			$post_id_inc = $this->gdMonitoreInsert($my_post);
			if ($post_id_inc) {
				$content_type = $this->gdMonitoreTipoArquivo($a->str_nome_fisico_arquivo);

				$arvalues = "";

				if ($content_type == "jpg" or $content_type == "png" or $content_type == "gif" or $content_type == "jpeg" ){
					set_post_format($post_id_inc, 'image' );
					$arvalues = $this->gdobras_prefix.$this->imagem;
				}
				else if ($content_type == "webm" or $content_type == "ogg" or $content_type == "mpr4" ){
					set_post_format($post_id_inc, 'video' );
					$arvalues = $this->gdobras_prefix.$this->video;
				}
				else {
					set_post_format($post_id_inc, 'link' );
					$arvalues = $this->gdobras_prefix.$this->arquivo;
				}

				$myvalues = array(
					$this->gdobras_prefix.$this->numcodigopk 	=> $a->num_codigo_pk,
					$arvalues => $a->url
				);

				$this->gdMonitoreInsertCustomField($post_id_inc, $myvalues);
			}

		}
	}

	function gdMonitoreInsertSituation($post_id, $situation){
		foreach ($situation as $a) {

			$title = $this->gdMonitoreMontaTituloSituation($a->dat_alteracao);
			$my_post = $this->gdMonitoreMontaPost(NULL, $title, $a->str_situacao, $a->dat_alteracao, $post_id);
			$post_id_parent = $this->gdMonitoreInsert($my_post);
			set_post_format($post_id_parent, 'status' );

			$myvalues = array(
				$this->gdobras_prefix.$this->numcodigopk 	=> $a->num_codigo_pk,
			);

			$this->gdMonitoreInsertCustomField($post_id_parent, $myvalues);
		}
		return $post_id_parent;
	}

	function gdMonitoreUpdate($my_post){
		$post_id =   wp_update_post( $my_post );
		return $post_id;
	}

	function gdMonitoreUpdateSituation($post_parent, $situation){
		foreach ($situation as $a) {
			$title 	= $this->gdMonitoreMontaTituloSituation($a->dat_alteracao);
			$val 	= $this->gdMonitoreVerificaCodigoPk($a->num_codigo_pk);
			if ($val) {
				$my_post = $this->gdMonitoreMontaPost($val, $title, $a->str_situacao, $a->dat_alteracao, $post_parent);
				$post_id_parent = $this->gdMonitoreUpdate($my_post);

			} else {
				$my_post = $this->gdMonitoreMontaPost(NULL, $title, $a->str_situacao, $a->dat_alteracao, $post_parent);
				$post_id_parent = $this->gdMonitoreInsert($my_post);
				set_post_format($post_id_parent, 'status' );
				$myvalues = array(
					$this->gdobras_prefix.$this->numcodigopk 	=> $a->num_codigo_pk,
				);
				$this->gdMonitoreInsertCustomField($post_id_parent, $myvalues);
			}
		}
		return $post_id_parent;
	}

	function gdMonitoreUpdateEvidences($post_id, $evidences){

		foreach ($evidences as $a) {

			$post_parent_id = $this->gdMonitorePostParent($post_id, $a->dat_documento);

			$val 	= $this->gdMonitoreVerificaCodigoPk($a->num_codigo_pk);
			if ($val) {
				$my_post = $this->gdMonitoreMontaPost($val, $a->str_titulo, $a->str_resumo, $a->dat_documento, $post_parent_id);
				$post_id_inc = $this->gdMonitoreUpdate($my_post);

				$content_type = $this->gdMonitoreTipoArquivo($a->str_nome_fisico_arquivo);

				$arvalues = "";

				if ($content_type == "jpg" or $content_type == "png" or $content_type == "gif" or $content_type == "jpeg"){
					set_post_format($val, 'image' );
					$arvalues = $this->gdobras_prefix.$this->imagem;
				}
				else if ($content_type == "webm" or $content_type == "ogg" or $content_type == "mpr4" ){
					set_post_format($val, 'video' );
					$arvalues = $this->gdobras_prefix.$this->video;
				}
				else {
					set_post_format($val, 'link' );
					$arvalues = $this->gdobras_prefix.$this->arquivo;
				}

				$myvalues = array(
					$arvalues => $a->url
				);

				$this->gdMonitoreInsertCustomField($val, $myvalues);

			} else {
				$my_post = $this->gdMonitoreMontaPost(NULL, $a->str_titulo, $a->str_resumo, $a->dat_documento, $post_parent_id);
				$post_id_inc = $this->gdMonitoreInsert($my_post);
				if ($post_id_inc) {
					$content_type = $this->gdMonitoreTipoArquivo($a->str_nome_fisico_arquivo);

					$arvalues = "";

					if ($content_type == "jpg" or $content_type == "png" or $content_type == "gif" or $content_type == "jpeg"){
						set_post_format($post_id_inc, 'image' );
						$arvalues = $this->gdobras_prefix.$this->imagem;
					}
					else if ($content_type == "webm" or $content_type == "ogg" or $content_type == "mpr4" ){
						set_post_format($post_id_inc, 'video' );
						$arvalues = $this->gdobras_prefix.$this->video;
					}
					else {
						set_post_format($post_id_inc, 'link' );
						$arvalues = $this->gdobras_prefix.$this->arquivo;
					}

					$myvalues = array(
						$this->gdobras_prefix.$this->numcodigopk 	=> $a->num_codigo_pk,
						$arvalues => $a->url
					);

					$this->gdMonitoreInsertCustomField($post_id_inc, $myvalues);
				}
			}

		}
	}


	function gdMonitoreVerificaCodigoPk($post_id){
		$sCodigoPk = $this->gdobras_prefix.$this->numcodigopk;

		$args = array( 	'post_type' => $this->postType
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
			'post_type'	     => $this->postType,
			'post_author'    => $this->gdMonitoreGetOption($this->userwp),
			'post_parent'    => $post_parent,
			'post_date'	     => $post_date,
			'post_date_gmt'  => date("Y-m-d H:i:s"),
			'comment_status' => 'open'
			);

		if( ! empty($title) ){
			$my_post['post_title'] = $title;
		}
		if( ! empty($content) ){
			$my_post['post_content'] = $content;
		}

		if(empty($post_id)){
			if(empty($post_parent)){
				#uma obra sempre entra como 'pending'
				$ar_status =  array('post_status' => "pending");
			}else{
				$ar_status =  array('post_status' => $this->statusPost);
			}
			$my_post = array_merge($my_post,$ar_status );
		}
		return $my_post;
	}

	function gdMonitoreTipoArquivo($arq){
		$arq = strrchr($arq, ".");
		$arq = strtolower(substr($arq,1,strlen($arq)));

		return $arq;
	}

	function gdMonitorePostParent($post_id, $dat_documento){

		global $wpdb;
		$sql = $wpdb->prepare("	SELECT  id
									FROM    wp_posts
									WHERE   post_parent in ($post_id) and post_type = 'gdobra'
									AND   	post_date <= '".$dat_documento."'
									ORDER   by post_date desc
									LIMIT   1");
		$post_parent_id = $wpdb->get_var($sql);

		if (!$post_parent_id){
			$sql = $wpdb->prepare("	SELECT  id
									FROM    wp_posts
									WHERE   post_parent in ($post_id) and post_type = 'gdobra'
									ORDER   by post_date asc
									LIMIT   1");
			$post_parent_id = $wpdb->get_var($sql);
		}
		return $post_parent_id;
	}

	function gdMonitoreMontaTituloSituation($datepost){

		$title = $this->gdTituloSituation." - ".substr($datepost,8,2).'/'.substr($datepost,5,2).'/'.substr($datepost,0,4);

		return $title;

	}


	function gdMonitoreMontaCamposCustom($porcExec,$dtinicio,$dtfim,$valor,$empresa,$objetivo,$projeto,$urlstream,$municipio,$sCodigoPk){


		$objetivo = !empty($objetivo->title) ? $objetivo->title : '';
		$projeto  = !empty($projeto->title)  ? $projeto->title  : '';
		$urlstream = '';
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
				if ($a->beneficiado == false){
					$mun[$cont] = $a->str_nome;
					$coo[$cont] = $a->num_latitude.",".$a->num_longitude;
					$cont++;
				}
			}
		} else {
			$mun = '';
			$coo = '';
		}

		// error_log("EMPRESA ========================================++++>");
		// error_log($empresa);
		$emp = split(",", $empresa);
		// error_log(print_r($emp, true));

		// error_log("COORDENADAS ====================================++++>");
		// error_log(print_r($coo, true));
		// // error_log($empresa);

		$my_post = array(
			$this->gdobras_prefix.$this->porc_concluido => $porcExec,
			$this->gdobras_prefix.$this->ini_efetivo 	=> $dtinicio,
			$this->gdobras_prefix.$this->fim_previsto 	=> $dtfim,
			$this->gdobras_prefix.$this->valor_global 	=> $valor,
			$this->gdobras_prefix.$this->emp_contratada	=> $emp,
			$this->gdobras_prefix.$this->objetivo 		=> $objetivo,
			$this->gdobras_prefix.$this->projeto 		=> $projeto,
			$this->gdobras_prefix.$this->stream 		=> $urlstream,
			$this->gdobras_prefix.$this->coordenadas 	=> $coo,
			$this->gdobras_prefix.$this->municipio 		=> $mun,
			$this->gdobras_prefix.$this->numcodigopk 	=> $sCodigoPk
			);

		return $my_post;
	}
function gdMonitoreLog($txt){
    	$arquivo = $_SERVER['DOCUMENT_ROOT']."/wp/wp-content/uploads/logSME.txt"; 
    	$fp = fopen($arquivo, "a");
		$txt = date('d/m/Y H:i:s')." - ".$txt."\n";
		$escreve = fwrite($fp, $txt);
		fclose($fp);
    }

}


$rsClasse = new gdMonitore();
$rsClasse->gdMonitoreLog("LOG INICIAL");

try{
	$url = $rsClasse->gdMonitoreMontaUrl();
$rsClasse->gdMonitoreLog("URL = ".$url);
	$rs = $rsClasse->gdMonitoreChamada($url);
$rsClasse->gdMonitoreLog("RS = ".$rs);
} catch (Exception $e){
	echo $e->getMessage();

}

foreach ($rs as $c){
	//Verifica se j existe o post da obra
	$val = $rsClasse->gdMonitoreVerificaCodigoPk($c->num_codigo_pk);
$rsClasse->gdMonitoreLog("codpk = ".$c->num_codigo_pk);
$rsClasse->gdMonitoreLog("VAL = ".$val);
	if ($val) {
$rsClasse->gdMonitoreLog("VAL EXISTE");
		//Se a obra j existe no DONO, no atualiza mais seu ttulo nem descrio.
		$my_post = $rsClasse->gdMonitoreMontaPost($val,"", "", date('Y-m-d H:i:s'),NULL);
$rsClasse->gdMonitoreLog("MYPOST = ".$my_post);
		$post_id = $rsClasse->gdMonitoreUpdate($my_post);
$rsClasse->gdMonitoreLog("POSTID= ".$post_id);
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
$rsClasse->gdMonitoreLog("MYVALUES = ".$myvalues);
		$rsClasse->gdMonitoreUpdateCustomField($post_id, $myvalues);
$rsClasse->gdMonitoreLog("Custom Fields");
		$post_Situation = $rsClasse->gdMonitoreUpdateSituation($val, $c->publicSituation);
$rsClasse->gdMonitoreLog("POSTSITUATION = ".$post_Situation);
		if ($post_Situation){
				$rsClasse->gdMonitoreUpdateEvidences($val, $c->evidences);
				$rsClasse->gdMonitoreLog("EVIDENCES = ".$c->evidences);
			}

		echo "<br>Atualizado com Sucesso<br>";
	} else {
$rsClasse->gdMonitoreLog("NO TEM VAL");
		$my_post = $rsClasse->gdMonitoreMontaPost(NULL,$c->str_titulo_obra, $c->str_descricao_olho_obra, date('Y-m-d H:i:s'),NULL);
$rsClasse->gdMonitoreLog("MYPOST = ".$my_post);
		$post_id = $rsClasse->gdMonitoreInsert($my_post);
$rsClasse->gdMonitoreLog("POSTID = ".$post_id);

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
$rsClasse->gdMonitoreLog("MYVALUES = ".$myvalues);

			$rsClasse->gdMonitoreInsertCustomField($post_id, $myvalues);
$rsClasse->gdMonitoreLog("Custom Fields");
			$post_Situation = $rsClasse->gdMonitoreInsertSituation($post_id, $c->publicSituation);
$rsClasse->gdMonitoreLog("POSTSITUATION = ".$post_Situation);
			if ($post_Situation){
				$rsClasse->gdMonitoreInsertEvidences($post_id, $c->evidences);
$rsClasse->gdMonitoreLog("EVIDENCES = ".$c->evidences);
			}

		}
		echo "<br>Inserido com Sucesso<br>";
		}

$rsClasse->gdMonitoreLog("LOG FINAL");
}
?>