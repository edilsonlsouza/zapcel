# **Addon de Notificações no WhatsApp para WHMCS**

Permite enviar notificações automáticas via WhatsApp para eventos de faturas no WHMCS, como criação, lembrete de pagamento, pagamento e cancelamento. Mensagens personalizadas são enviadas pela API da Zapcel.

## **Funcionalidades**

- **Envio Automático**: Notificações para:
  - Criação de fatura
  - Fatura paga
  - Lembrete de pagamento
  - Fatura cancelada
- **Mensagens Personalizadas**: Incluem nome do cliente, valor, método de pagamento, produtos e links com AutoLogin.
- **Integração com API**: Mensagens enviadas diretamente pela API da Zapcel.

## **Instalação**
1. Baixe e extraia os arquivos do módulo.
2. Insira no diretório `/modules/addons` do WHMCS.
3. Configure e teste a API da Zapcel.

## **Dependências**
- **API Zapcel**: [Painel Zapcel](https://zap.hostcel.com.br/login)
- **Token de Acesso**: Token único gerado durante o cadastro no Zapcel.
- **ID da Instância**: ID gerado ao ler o QR Code no Zapcel.


## **Personalização**
- Ajuste mensagens e adicione eventos nas funções `enviarMensagemWhatsApp` e `enviarMensagemPix`.

## **Eventos Suportados**
- **Criação de Fatura**: Notifica sobre novas faturas.
- **Fatura Paga**: Confirma pagamento.
- **Fatura Cancelada**: Informa cancelamentos.
- **Lembrete de Pagamento**: Lembra faturas pendentes.
- **Fatura Pix**: Envia código Pix para pagamento.

## **Detalhes Técnicos**
- **Processamento de Números**: Remove caracteres extras (+, .) para compatibilidade com a API.
- **Mensagens Dinâmicas**:
  - Valor formatado em BRL.
  - Método de pagamento amigável (Pix, MercadoPago, Stripe, etc.).
  - Produtos vinculados à fatura.
  - Links com AutoLogin.

## **Requisições API**
- Mensagens enviadas via cURL para o endpoint da API da Zapcel com autenticação pela `Token de Acesso`.

## **Contribuições e Suporte**
- Relate erros nos logs do WHMCS.
- Sugira melhorias ou novos recursos.
- Logs de debug disponíveis para diagnóstico.

## **Licença**
Módulo open source. Uso, modificação e distribuição permitidos.

## GitHub
- [GitHub Oficial](https://github.com/ianchamba/whatsapp-whmcs)
- [Ian Chamba](https://github.com/ianchamba)  
- [OpenPix](https://openpix.com.br/)
- [API da Zapcel](https://documenter.getpostman.com/view/16327062/2s93Jut3CQ)

### *All rights reserved to their respective authors*

