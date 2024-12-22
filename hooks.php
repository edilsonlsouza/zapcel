<?php

if (!defined("WHMCS")) die("Acesso restrito.");

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;
/**
 * Processa o número de telefone para garantir que ele siga o formato correto.
 *
 * @param string $phonenumber O número de telefone no formato original.
 * @return string O número de telefone processado.
 */
function processarNumeroTelefone($phonenumber) {
    // Remove caracteres '+' e '.'
    return str_replace(['+', '.'], '', $phonenumber);
}

function getTemplate($event)
{
    // Templates padrão
    $defaultTemplates = [
        'InvoiceCreation' => "*Fatura em Aberto* \n\n".
            "Olá {primeiro_nome}, tudo bem? ☺️\n\n".
            "🔖 Você possui uma fatura em aberto #{id_fatura}.\n".
            "💵 Valor: R$ {valor}\n".
            "💳 Método de Pagamento: {metodo_pagamento}\n".
            "📦 Produtos: {produtos_lista}\n\n".
            "Para acessar sua fatura, clique no link abaixo:\n\n".
            "{link_fatura}\n\n".
            "_Equipe Hostcel_",
        'InvoicePaid' => "*Fatura Paga* \n\n".
            "Olá {primeiro_nome}, tudo bem? ☺️\n\n".
            "✅ Sua fatura #{id_fatura} foi paga com sucesso.\n".
            "💵 Valor: R$ {valor}\n".
            "💳 Método de Pagamento: {metodo_pagamento}\n".
            "📦 Produtos: {produtos_lista}\n\n".
            "Obrigado por escolher nossos serviços! Estamos sempre à disposição. 💙" . "\n\n" .
            "_Equipe Hostcel_",
        'InvoiceCancelled' => "*Fatura Cancelada* \n\n".
            "Olá {primeiro_nome}, tudo bem? ☺️\n\n".
            "⚠️ Informamos que a fatura #{id_fatura} foi cancelada.\n".
            "💵 Valor: R$ {valor}\n".
            "💳 Método de Pagamento: {metodo_pagamento}\n".
            "📦 Produtos: {produtos_lista}\n\n".
            "Se precisar de mais informações ou ajuda, estamos à disposição. 💙" . "\n\n" .
            "_Equipe Hostcel_",
        'InvoicePaymentReminder' => "*Lembrete de Pagamento* \n\n".
            "Olá {primeiro_nome}, tudo bem? ☺️\n\n".
            "Esta mensagem é apenas um lembrete referente a fatura #{id_fatura} gerada em {data_geracao} com vencimento {data_vencimento}.\n".
            "💵 Valor: R$ {valor}\n".
            "💳 Método de Pagamento: {metodo_pagamento}\n".
            "📦 Produtos: {produtos_lista}\n\n".
            "Para acessar sua fatura, clique no link abaixo:\n\n".
            "{link_fatura}\n\n" .
            "_Equipe Hostcel_",
        'LateInvoicePaymentReminder' => "*Fatura em Atraso* \n\n" .
            "Olá {primeiro_nome}, tudo bem? ☺️\n\n" .
            "Gostaríamos de lembrá-lo de que a fatura #{id_fatura}, gerada em {data_geracao}, com vencimento em {data_vencimento}, ainda está pendente. Para evitar qualquer interrupção nos seus serviços, pedimos que regularize o pagamento o quanto antes.\n" .
            "💵 Valor: R$ {valor}\n" .
            "💳 Método de Pagamento: {metodo_pagamento}\n" .
            "📦 Produtos: {produtos_lista}\n\n" .
            "Para acessar sua fatura, clique no link abaixo:\n\n" .
            "{link_fatura}\n\n" .
            "_Equipe Hostcel_",
    ];

    // Busca o template na tabela `tblzapcel`
    $template = Capsule::table('tblzapcel')
        ->where('event', $event)
        ->value('template');

    // Retorna o template encontrado ou o padrão
    return $template ?? $defaultTemplates[$event] ?? "Template não configurado.";
}

/**
 * Envia uma mensagem no WhatsApp usando a API especificada com dados da fatura e do cliente.
 *
 * @param string $event O nome do evento (InvoiceCreation, InvoicePaid, InvoiceCancelled).
 * @param array $invoiceData Dados da fatura a serem enviados na mensagem.
 * @param array $clientData Dados do cliente a serem enviados na mensagem.
 */
// Função de envio de mensagem no WhatsApp com link de autologin
function enviarMensagemWhatsApp($event, $invoiceData, $clientData) {
    
    // Recupera as configurações do módulo
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'Zapcel')
        ->pluck('value', 'setting');

    // Configurações dinâmicas
    $apiKey = $settings['apiKey'] ?? 'default_api_key';
    $whatsAppInstance = $settings['whatsAppInstance'] ?? 'DefaultInstance';

    // URL da API
    $url = "https://zap.hostcel.com.br/api/send";
    
    // Processa o número de telefone
    $number = processarNumeroTelefone($clientData['phonenumberformatted']);
        
    $produtos = array_map(function($item) {
        return trim(preg_replace('/\s*\(\d{2}\/\d{2}\/\d{4} - \d{2}\/\d{2}\/\d{4}\)(.|\s)*/', '', $item['description']));
    }, $invoiceData['items']['item']);
    
    $template = getTemplate($event);

    $placeholders = [
        '{primeiro_nome}' => trim($clientData['firstname']),
        '{id_fatura}' => $invoiceData['invoiceid'],
        '{metodo_pagamento}' => $metodoPagamento = $invoiceData['paymentmethod'] === 'openpix' 
        ? 'Pix' 
        : ($invoiceData['paymentmethod'] === 'mercadopago_1' 
            ? 'MercadoPago' 
            : ($invoiceData['paymentmethod'] === 'stripe' 
                ? 'Cartão de Crédito/Débito' 
                : $invoiceData['paymentmethod'])),
        '{valor}' => number_format($invoiceData['total'], 2, ',', '.'),
        '{data_geracao}' => DateTime::createFromFormat('Y-m-d', $invoiceData['date'])->format('d/m/Y'),
        '{data_vencimento}' => DateTime::createFromFormat('Y-m-d', $invoiceData['duedate'])->format('d/m/Y'),
        '{produtos_lista}' => implode(", ", $produtos),
        //'{link_fatura}' => rtrim(Setting::getValue('SystemURL'), "/") . "/viewinvoice.php?id={$invoiceData['invoiceid']}" //gerarLinkAutoLogin($clientData['id'], 'clientarea', "viewinvoice.php?id={$invoiceData['invoiceid']}"),
        '{link_fatura}' => function_exists('gerarLinkAutoLogin') 
        ? gerarLinkAutoLogin($clientData['id'], 'clientarea', "viewinvoice.php?id={$invoiceData['invoiceid']}") 
        : rtrim(Setting::getValue('SystemURL'), "/") . "/viewinvoice.php?id={$invoiceData['invoiceid']}",
    ];

    // Substituir variáveis no template
    $text = str_replace(array_keys($placeholders), array_values($placeholders), $template);

    $headers = [
        'Content-Type: application/json'
    ];

    $body = [
        "number" => $number,
        "type" => "text",
        "message" => $text,
        "instance_id" => $whatsAppInstance,
        "access_token" => $apiKey
    ];

    // Log de debug - dados da mensagem
    error_log("Preparando envio de mensagem para o evento '$event' com número '$number' e dados: " . json_encode($body));

    // Inicializa o cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execução da solicitação
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // Verificação e log de erro
    if ($response === false) {
        error_log("Erro no envio da mensagem para o evento '$event'. Erro: $error");
    } else {
        error_log("Mensagem enviada com sucesso para o evento '$event'. Código HTTP: $httpcode, Resposta: $response");
    }

    // Fecha o cURL
    curl_close($ch);
}

function enviarMensagemPix($event, $invoiceData, $clientData) {
    
    // Recupera as configurações do módulo
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'Zapcel')
        ->pluck('value', 'setting');

    // Configurações dinâmicas
    $apiKey = $settings['apiKey'] ?? '650bd52711fa4';
    $whatsAppInstance = $settings['whatsAppInstance'] ?? 'DefaultInstance';

    // URL da API
    $url = "https://zap.hostcel.com.br/api/send";
    
    // Processa o número de telefone
    $number = processarNumeroTelefone($clientData['phonenumberformatted']);

    // Define o conteúdo da mensagem conforme o status da fatura
    if ($invoiceData['paymentmethod'] === 'openpix' && $invoiceData['status'] === 'Unpaid') {
        
        // Tentativas para obter o brCode
        $tentativas = 0;
        $maxTentativas = 5;
        $brCode = null;

        while ($tentativas < $maxTentativas && !$brCode) {
            // Busca o registro da fatura e acessa a coluna 'brCode'
            $invoice = Capsule::table('tblinvoices')
                ->where('id', $invoiceData['invoiceid'])
                ->first();
                error_log("Metadata Pix: " . print_r($invoice, true));
            
            if ($invoice && isset($invoice->brCode)) {
                $brCode = $invoice->brCode;
                error_log("brCode encontrado: $brCode");
            } else {
                $brCode = null;
                error_log("brCode não encontrado na tentativa " . ($tentativas + 1) . ". Retentando em 1 segundo...");
                sleep(1);
            }

            $tentativas++;
        }

        if (!$brCode) {
            $textPart1 = "*Código Pix Não Encontrado*";
            error_log("Falha ao encontrar brCode após $maxTentativas tentativas.");
        } else {
            $textPart1 = "*Copie o código Pix abaixo para efetuar o pagamento:* 👇";
            $textPart2 = $brCode;
        }

        $headers = [
            'Content-Type: application/json'
        ];
    
        // Função para enviar mensagens
        $sendMessage = function($text, $additionalFields = []) use ($url, $headers, $number, $event) {

            $body = array_merge([
                "number" => $number,
                "type" => "text",
                "message" => $text,
                "instance_id" => $whatsAppInstance,
                "access_token" => $apiKey
            ], $additionalFields);

            // Log de debug - dados da mensagem
            error_log("Preparando envio de mensagem para o evento '$event' com número '$number' e dados: " . json_encode($body));

            // Inicializa o cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execução da solicitação
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            // Verificação e log de erro
            if ($response === false) {
                error_log("Erro no envio da mensagem para o evento '$event'. Erro: $error");
            } else {
                error_log("Mensagem enviada com sucesso para o evento '$event'. Código HTTP: $httpcode, Resposta: $response");
            }

            // Fecha o cURL
            curl_close($ch);

        };

        // Enviar a primeira parte da mensagem
        $sendMessage($textPart1);

        // Enviar a segunda parte da mensagem (se aplicável)
        if (isset($textPart2)) {
            $sendMessage($textPart2, ["delay" => 3000]);
        }
    }

}

function enviarMensagemWhatsAppAffiliate($number, $text) {
    
    // Recupera as configurações do módulo
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'WhatsAppNotify')
        ->pluck('value', 'setting');

    // Configurações dinâmicas
    $apiKey = $settings['apiKey'] ?? 'default_api_key';
    $whatsAppInstance = $settings['whatsAppInstance'] ?? 'DefaultInstance';

    // URL da API
    $url = "https://zap.hostcel.com.br/api/send";
    
    $headers = [
        'Content-Type: application/json'
    ];

    $body = [
        "number" => $number,
        "type" => "text",
        "message" => $text,
        "instance_id" => $whatsAppInstance,
        "access_token" => $apiKey
    ];

    // Log de debug - dados da mensagem
    error_log("Preparando envio de mensagem para número '$number' com dados: " . json_encode($body));

    // Inicializa o cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execução da solicitação
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // Verificação e log de erro
    if ($response === false) {
        error_log("Erro no envio da mensagem. Erro: $error");
    } else {
        error_log("Mensagem enviada com sucesso. Código HTTP: $httpcode, Resposta: $response");
    }

    // Fecha o cURL
    curl_close($ch);

}

// Hook para InvoiceCreation
add_hook('InvoiceCreation', 1, function($vars) {
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceid']]);
    $clientData = localAPI('GetClientsDetails', ['clientid' => $invoiceData['userid'], 'stats' => true]);
    error_log("Hook 'InvoiceCreation' ativado. Dados da Fatura: " . print_r($invoiceData, true) . " Dados do Cliente: " . print_r($clientData, true));
    enviarMensagemWhatsApp('InvoiceCreation', $invoiceData, $clientData);
});

add_hook('OpenpixInvoiceGenerated', 1, function($vars) {
    $invoiceId = $vars['invoiceId'];

    // Verifica se o invoice já foi processado
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if ($invoice && $invoice->processed) {
        error_log("Invoice ID $invoiceId já foi processado. Ignorando.");
        return;
    }

    // Obtenha os dados da fatura e do cliente e envie a mensagem
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
    $clientData = localAPI('GetClientsDetails', ['clientid' => $invoiceData['userid'], 'stats' => true]);
    error_log("Hook 'OpenpixInvoiceGenerated' triggered. Invoice Data: " . print_r($invoiceData, true) . " Client Data: " . print_r($clientData, true));
    enviarMensagemPix('OpenpixInvoiceGenerated', $invoiceData, $clientData);

    // Atualiza o status de processamento no banco de dados
    Capsule::table('tblinvoices')->where('id', $invoiceId)->update(['processed' => 1]);
});

// Hook para InvoicePaid
add_hook('InvoicePaid', 1, function($vars) {
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceid']]);
    $clientData = localAPI('GetClientsDetails', ['clientid' => $invoiceData['userid'], 'stats' => true]);
    error_log("Hook 'InvoicePaid' ativado. Dados da Fatura: " . print_r($invoiceData, true) . " Dados do Cliente: " . print_r($clientData, true));
    enviarMensagemWhatsApp('InvoicePaid', $invoiceData, $clientData);
});

// Hook para InvoiceCancelled
add_hook('InvoiceCancelled', 1, function($vars) {
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceid']]);
    $clientData = localAPI('GetClientsDetails', ['clientid' => $invoiceData['userid'], 'stats' => true]);
    error_log("Hook 'InvoiceCancelled' ativado. Dados da Fatura: " . print_r($invoiceData, true) . " Dados do Cliente: " . print_r($clientData, true));
    enviarMensagemWhatsApp('InvoiceCancelled', $invoiceData, $clientData);
});

// Hook para InvoicePaymentReminder
add_hook('InvoicePaymentReminder', 1, function($vars) {
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceid']]);
    $clientData = localAPI('GetClientsDetails', ['clientid' => $invoiceData['userid'], 'stats' => true]);
    error_log("Hook 'InvoicePaymentReminder' ativado. Dados da Fatura: " . print_r($invoiceData, true) . " Dados do Cliente: " . print_r($clientData, true));
    $dueDate = $invoiceData['duedate'];
    $currentDate = date('Y-m-d');
    if ($currentDate > $dueDate) {
        enviarMensagemWhatsApp('LateInvoicePaymentReminder', $invoiceData, $clientData);
    } else {
        enviarMensagemWhatsApp('InvoicePaymentReminder', $invoiceData, $clientData);
    }
});

// Hook para AffiliateWithdrawalRequest para enviar notificação de solicitação de retirada.

add_hook('AffiliateWithdrawalRequest', 1, function($vars) {
    // Log detalhado do conteúdo da variável $vars para depuração
    error_log("Hook 'AffiliateWithdrawalRequest' ativado. Dados do Hook: " . print_r($vars, true));

    $affiliateId = $vars['affiliateId'] ?? 'Desconhecido';
    $userId = $vars['userId'] ?? 'Desconhecido';
    $clientId = $vars['clientId'] ?? 'Desconhecido';
    $balanceFormatado = $vars['balance'] ?? 'Desconhecido';
    $balance = number_format($balanceFormatado, 2, ',', '.');

    // Obter informações do afiliado
    $user = Capsule::table('tblclients')->where('id', $userId)->first();
    $firstName = $user->firstname ?? 'Nome Desconhecido';
    $email = $user->email ?? 'E-mail Desconhecido';
    $phoneNumber = $user->phonenumber ?? null;
    $phoneNumberFormatted = processarNumeroTelefone($phoneNumber);

    // Mensagem para o administrador
    $adminText = "*Notificação de Solicitação de Retirada de Afiliado* \n\n" .
                "🔔 Um afiliado solicitou retirada de comissão.\n" .
                "🆔 ID do Afiliado: $affiliateId\n" .
                "👤 Nome: $firstName\n" .
                "📧 E-mail: $email\n" .
                "👥 ID do Cliente: $clientId\n" .
                "💰 Saldo da Conta: R$ $balance\n\n" .
                "_Equipe Hostcel_";

    // Envia a mensagem para o administrador
    enviarMensagemWhatsAppAffiliate('5561995940410', $adminText);

    // Mensagem para o afiliado, caso o número de telefone esteja disponível
    if ($phoneNumberFormatted) {
        $affiliateText = "*Solicitação de Retirada Recebida* \n\n" .
                        "Olá $firstName,\n\n" .
                        "🔔 Recebemos sua solicitação de retirada de comissão no valor de R$ $balance. " .
                        "Nossa equipe está analisando e em breve você será notificado sobre o andamento.\n\n" .
                        "_Equipe Hostcel_";

        enviarMensagemWhatsAppAffiliate($phoneNumberFormatted, $affiliateText);
    } else {
        error_log("Número de telefone não encontrado para o afiliado com ID '$affiliateId'.");
    }
});