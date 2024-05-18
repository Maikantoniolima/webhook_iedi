<?php
/*
Plugin Name: Webhook IEDI
Plugin URI: https://documenter.getpostman.com/view/11729311/2s9Xxtyvc1
Description: Plugin de integração entre o woocormerce e site de estudo da IEDI || Atualizado em 23/08/2023
Version: 1.7.2
Author: Maik Antonio Costa de Lima
Author URI: https://www.behance.net/maikantoniolima
License: GPL2
*/

add_action( 'admin_menu', 'webhook_iedi_menu' );

function webhook_iedi_menu() {
    add_submenu_page(
        'woocommerce',
        'Webhook IEDI',
        'Webhook IEDI',
        'manage_options',
        'webhook-iedi',
        'webhook_iedi_pagina'
    );
}

function webhook_iedi_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=webhook-iedi">Configurações</a>';
    array_push( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'webhook_iedi_settings_link' );

function webhook_iedi_ver_detalhes() {
    echo '<h1>Detalhes do Webhook IEDI</h1>';
    echo '<p>Aqui estão os detalhes do seu plugin.</p>';
}

function webhook_iedi_pagina() {
    ?>
    <div class="wrap">
        <h1>Webhook IEDI</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'webhook-iedi-settings' ); ?>
            <?php do_settings_sections( 'webhook-iedi-settings' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Selecione status que faz o disparo</th>
                    <td>
                        <select name="status">
                                <option value="woocommerce_order_status_pending" <?php selected( 'woocommerce_order_status_pending', get_option( 'status' ) ); ?>>Pendente</option>
                                <option value="woocommerce_order_status_processing" <?php selected( 'woocommerce_order_status_processing', get_option( 'status' ) ); ?>>Processando</option>
                                <option value="woocommerce_order_status_completed" <?php selected( 'woocommerce_order_status_completed', get_option( 'status' ) ); ?>>Concluído</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">URL</th>
                    <td><input type="text" name="url" value="<?php echo esc_attr( get_option('url') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Chave</th>
                    <td><input type="text" name="chave" value="<?php echo esc_attr( get_option('chave') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">URL de acesso do Ambiente</th>
                    <td><input type="text" name="urlambiente" value="<?php echo esc_attr( get_option('urlambiente') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Ativar contrato?</th>
                    <td>
                        <select name="contratoativo">
                                <option value = "sim" <?php selected( 'sim', get_option( 'contratoativo' ) ); ?>>Sim</option>
                                <option value = "nao" <?php selected( 'nao', get_option( 'contratoativo' ) ); ?>>Não</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function webhook_iedi_registrar_opcoes() {
    register_setting( 'webhook-iedi-settings', 'status' );
    register_setting( 'webhook-iedi-settings', 'url' );
    register_setting( 'webhook-iedi-settings', 'chave' );
    register_setting( 'webhook-iedi-settings', 'urlambiente' );
    register_setting( 'webhook-iedi-settings', 'contratoativo' );
}
add_action( 'admin_init', 'webhook_iedi_registrar_opcoes' );


/*Adicionar Campo no Woocomerce*/
add_action( 'woocommerce_product_options_general_product_data', 'woocommerce_product_custom_fields' );
function woocommerce_product_custom_fields() {
    global $woocommerce, $post;
    echo '<div class="product_custom_field">';
    // Campo personalizado para o Plano ID
    woocommerce_wp_text_input(
        array(
            'id' => '_plano_id',
            'placeholder' => 'Plano ID',
            'label' => __('Plano ID', 'woocommerce'),
            'type'        => 'number',
            'description' => __( 'Enter a number', 'woocommerce' ),
        )
    );
    echo '</div>';
}

add_action( 'woocommerce_process_product_meta', 'woocommerce_product_custom_fields_save' );
function woocommerce_product_custom_fields_save( $post_id ) {
    // Salva o campo personalizado para o Plano ID
    $woocommerce_plano_id = $_POST['_plano_id'];
    if (!empty($woocommerce_plano_id))
        update_post_meta( $post_id, '_plano_id', esc_attr( $woocommerce_plano_id ) );
}


/*Função que dispara para o webhook que está cadastrado no menu ->Woocomerce -> Webhook*/
$status = get_option('status');
add_action( $status, 'enviar_dados_api_externa' );


function enviar_dados_api_externa( $order_id) {

    //Verifica se no painel do webhook o contrato está ativo: sim ou nao
    $contratoativo_opt = get_option('contratoativo'); 
    //Id da ordem
    $order = wc_get_order( $order_id );
    // Recupera o ID do produto
    $items = $order->get_items();
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        break;
    }

    //Dentro dos configurações do plugin contrato
    //Verifiqua se qual o tipo de contrato está ativo, se geral ou por produto
    $configGeracaoContratos = get_option('configGeracaoContratos');
    
    //se for geral
    if ( $configGeracaoContratos == 'geral' ) {
        //recebe qual o modelo de contrato foi ativado nas configuraçoes de contratos 
        $modeloContratogeral= get_option('modeloContrato');
        $urlpassada =  $modeloContratogeral;
    }else{
        //então é por_produto     
        $modeloContratoprod = get_post_meta( $product_id, 'modelo_de_contrato_para_o_produto', true );
        $urlpassada = $modeloContratoprod;    
    }

    
    if(!empty( $urlpassada )){

        //identifica a url conforme o id
        $post = get_post ($urlpassada);
        $post_type = $post->post_type;
        $post_name = $post->post_name;
        // Obter a url do site
        $urldosite = get_site_url();
        // Retornar a url completa do post
        $url = $urldosite . '/' . $post_type . '/' . $post_name;

        
    }else {

        //Se a UrlPassada estiver vazia quer dizer que o produto não tem contrato anexado
        update_post_meta($order_id, 'Contrato', "Ordem sem Contrato");  

        return false;
    }


    //Teste de verificação de retorno de variável
    $contrato_assinado_raw = $order->get_meta( 'contrato_assinado_raw_html' );

    // variáveis de retorno
    update_post_meta($order_id, 'dados_carregados', 
    "Contrato Ativo: ".$contratoativo_opt  
    ." Orden N: " .$order_id 
    ." Id do Produto: " .$product_id  
    ." Configuração dos Contratos: " .$configGeracaoContratos 
    ." URL do post com o contrato: " .$url ."/?pedido=" .$urlpassada );  



    // O contrato está ativado e o modelo de contrato deve estar com o id

    if($contratoativo_opt == 'sim' && !empty($urlpassada)){
        // Obter o contrato assinado em HTML    
        //$contrato_assinado_raw_html = $order->get_meta( '_contrato_assinado_raw_html' );

        if ( empty($contrato_assinado_raw)) {
            // Se estiver vazio, definir a mensagem como "Seu contrato está pendente"
                           
            $mensagem = "Seu contrato está pendente, assine nesse link: 
                <a href=\"$url/?pedido=$order_id\">
                <button style=\"background-color: #f44336; border: none;color: white;padding: 15px 32px;text-align: center;text-decoration: none;display: inline-block;font-size: 16px;margin: 4px 2px;cursor: pointer;\">
                Clique aqui para assinar seu Contrato</button></a>";
                
            // Adicionar a nota para o cliente
            $order->add_order_note( $mensagem, true ); 
        
    
               
        } else {        
            $nome = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $email = $order->get_billing_email();
            $telefone = get_post_meta( $order_id, '_billing_phone', true );
            $cpf = get_post_meta( $order_id, '_billing_cpf', true );
            $cnpj = get_post_meta( $order_id, '_billing_cnpj', true );
    
            if ( isset( $cpf ) && ! empty( $cpf ) ) {
                // atribuir o resultado à variável $cpf
                $resultado = $cpf;
            } else if ( isset( $cnpj ) && ! empty( $cnpj ) ) {
                // atribuir o resultado à variável $cnpj
                $resultado = $cnpj;
            }
            // remover os pontos, traços e barras do resultado
            $resultado = str_replace( array( '.', '-', '/' ), '', $resultado );    
            
            // Recupera o valor do meta "Plano" para o produto em questão
            $plano_id = get_post_meta( $product_id, '_plano_id', true );
    
            $url = get_option('url');
            $chave = get_option('chave');
            $urlambiente = get_option('urlambiente');
    
            //Identificado externo trocado para o numero da order, devido a problemas com ID de usuário visitante
    
            $data = array(
                "plano_id" => $plano_id,
                "nome" => $nome,
                "email" => $email,
                "senha" => $resultado,
                "telefone" => $telefone,
                "cpf" => $cpf .$cnpj,
                "identificador_externo" => $order_id
            );
    
            // Enviar a requisição POST para a API externa
            $resposta = wp_remote_post( $url, array(
                'headers' => array(
                    'token' => $chave,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data)
            ) );
    
            // Obter o corpo da resposta como uma string
            $corpo = wp_remote_retrieve_body($resposta);
            $data_atual = date("d/m/Y H:i:s");
    
            // Verificar se houve algum erro ou se o código de status HTTP foi diferente de 200
            if ( is_wp_error ( $resposta ) || wp_remote_retrieve_response_code ( $resposta ) != 201 ) {
                //atribui a resposta     
                update_post_meta($order_id, 'resposta_api',"negado: " .$corpo .$data_atual); 
                
                return false;
            }
    
            
            // Salvar o valor do corpo no campo personalizado "resposta_api" no banco de dados
            update_post_meta($order_id, 'resposta_api', "Enviado: ".$corpo);  
             
    
            // Definir a mensagem da nota
            $mensagem = "<h2 style=\"text-align: center;\" >Acesse a plataforma de ensino</h2>
                        <div style=\"background-color:#f2f2f2; padding: 5px; text-align: center;\">
                        Através deste link::
                        <a href=\"$urlambiente\">$urlambiente</a>
                        Utilize seu endereço de email: <b>$email</b> como login e seu <b>CPF:</b> $resultado, sem a pontuação, como senha.";

            // Adicionar a nota para o cliente
            $order->add_order_note( $mensagem, true ); 
    
    
        } 

    }

}

/*
    incluir função do IEDI webhook no plugin-woocomerce-contratro dentro da função funcao_salvar_contrato_assinatura(){
    
        ------------------------
            function funcao_salvar_contrato_assinatura(){
                
                $htmlContrato            = $_POST["htmlContrato"];
                $id_pedido_woo_contratos = $_POST["id_pedido_woo_contratos"];

                update_post_meta( $id_pedido_woo_contratos, 'contrato_assinado_raw_html', $htmlContrato );

                    if (file_exists( ABSPATH . "wp-content/plugins/Webhook-IEDI/Webhook-IEDI.php")) {
                    // Insere o require com o operador @
                    require_once ( ABSPATH . 'wp-content/plugins/Webhook-IEDI/Webhook-IEDI.php' );;

                    // Chama a função do plugin webhook com um parâmetro
                    enviar_dados_api_externa( $id_pedido_woo_contratos);
                }

            

                $response = array("status" => 200, "infos" => $_POST);   

                wp_send_json( $response );

            }

        ---------------------

 */