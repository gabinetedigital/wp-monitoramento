<?php /* -*- Mode: php; c-basic-offset:4; -*- */
/* Copyright (C) 2014  Sergio Berlotto <sergio-berlotto@sgg.rs.gov.br>
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Notificações - De Olho nas Obras</title>
    <style>
    table{
        width: 100%;
    }
    table th{
        background-color: #5A5A5A;
        color: #fff;
    }
    table tr:hover{
        background-color: rgba(130,130,130,.3);
    }
    table{
        border: 1px solid black;
    }
    td{
        border-right: 1px dashed red;
        padding-right: 8px;
    }
    .warning{
        color: red;
        font-weight: bold;
    }
    </style>
</head>
<body>

<?php

$mail_recipients = array("sergio.berlotto@gmail.com","sergio-berlotto@sgg.rs.gov.br");

function calcula_data_r2($sdataR2){
    $dias_intervalo = 32;
    // $sdataR2 = "01/02/2014";

    $dataR2 = DateTime::createFromFormat('Y-m-d', $sdataR2);
    // $dataR2 = DateTime::createFromFormat('d/m/Y', $sdataR2);

    $hoje = date_create();

    $intervalo = date_diff($hoje, $dataR2);
    $out = $intervalo->format("%Y Years, %M Months and %d Days");
    $dias = $intervalo->y * 365.25 + $intervalo->m * 30 + $intervalo->d + $intervalo->h/24 + $intervalo->i / 60;

    $resto = $dias % $dias_intervalo;

    $prox = clone $hoje;

    $prox->add(new DateInterval("P".$resto."D"));
    $datas = array();
    $datas['proxima'] = $prox;

    $ant = clone $prox;
    $ant->sub(new DateInterval("P".$dias_intervalo."D"));
    $datas['anterior'] = $ant;

    $penultima = clone $ant;
    $penultima->sub(new DateInterval("P".($dias_intervalo)."D"));
    $datas['penultima'] = $penultima;

    return $datas;
}

function eh_dia_de_aviso($database){
    //Esta rotina verifica se é dia de avisar a alguem do atraso na atualização
    //da obra.
    $periodicidade = 3; #em dias

    // $dataR2 = DateTime::createFromFormat('Y-m-d', $database);
    $hoje = date_create();

    $data_aviso = clone $database;
    while (true){
        if($data_aviso == $hoje){
            return true;
            break;
        }
        if($data_aviso > $hoje){
            return false;
            break;
        }
        $data_aviso->add(new DateInterval("P".$periodicidade."D"));
    }
}

//Busca as obras que não tiveram ainda Governo Informa entre a ultima R2 e a
global $wpdb;
$sql = $wpdb->prepare(" SELECT * FROM (
                            SELECT p.id, p.post_title title, DATE(p.post_date) post_date, p.post_status, p.post_parent,
                                   p.post_type, pai.post_title parent_title, pai.id parent_id, pai.post_name parent_slug
                            FROM wp_posts p join wp_posts pai on p.post_parent = pai.id
                            WHERE p.id in (
                                SELECT object_id FROM wp_term_relationships
                                    WHERE term_taxonomy_id = (
                                        SELECT a.term_taxonomy_id
                                        FROM `wp_term_taxonomy` a JOIN wp_terms b on a.term_id = b.term_id
                                        WHERE b.name = 'post-format-status')
                                )
                            AND p.post_type = 'gdobra'
                            ORDER BY pai.post_title, p.post_date DESC
                        ) t GROUP BY parent_title");

$obras = $wpdb->get_results($sql);
?>

<table>
<tr>
    <th>Id</th>
    <th>PaiID</th>
    <th>Obra</th>
    <th>Ult. Status</th>
    <th>Data Status</th>
    <th>Data Penultima R2</th>
    <th>Data Ultima R2</th>
    <th>Data Proxima R2</th>
</tr>
<?php

$obra_atrasada_subject = get_option("gd_obra_atrasada_subject");
$obra_atrasada_msg = get_option("gd_obra_atrasada_msg");
$baseurl = get_option("gd_base_url");

foreach($obras as $obra){
    $pdate = new DateTime($obra->post_date);
    $data_ultima_r2_obra = get_post_custom_values("gdobra_ultima_r2", $obra->parent_id);
    // print "<tr><td colspan=3>$obra->parent_id,$obra->parent_title,".gettype($data_ultima_r2_obra[0])."</td></tr>";
    // print "<tr><td colspan=3>".$data_ultima_r2_obra[0]."</td></tr>";

    if( $data_ultima_r2_obra != null ){
        // echo "NAO EMPTY";
        $data = calcula_data_r2($data_ultima_r2_obra[0]);
        // echo "Proxima R2: ", $data['proxima']->format('d/m/Y');
        // echo "<BR>R2 Anterior: ", $data['anterior']->format('d/m/Y');
        // echo "<BR>R2 Penultima: ", $data['penultima']->format('d/m/Y');

        $url = $baseurl."/deolho/obra/".$obra->parent_slug;
        echo "<tr><td>$obra->id</td><td>$obra->parent_id</td><td><a href='$url' target='_blank'>$obra->parent_title</a>";

        if ( $pdate < $data['penultima'] ){ #Se a obra está com sua atualização atrasada

            if ( eh_dia_de_aviso($data['anterior']) ){ #avisa somente no dia ou de 3 em 3 dias

                echo " <span class='warning'>[ATRASADA]</span>";
                $to = get_post_custom_values("gdobra_atrasada_emails1", $obra->parent_id);
                if( $to != null ){
                    echo "<br><span>Emails para: $to[0]</span>";
                    wp_mail( $to, $obra_atrasada_subject, sprintf($obra_atrasada_msg, $obra->title, $url) );
                }

            }

        }
        echo "</td><td>$obra->title</td><td>", $pdate->format("Y-m-d"), "</td>";
        echo "<td>",$data['penultima']->format('Y/m/d'),"</td><td>",$data['anterior']->format('Y/m/d'),"</td><td>",$data['proxima']->format('Y/m/d'),"</td></tr>";
    }
}
wp_reset_query();
?>
</table>

</body>
</html>
