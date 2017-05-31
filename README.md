# DashPayments para WooCommerce 

DashPayments para WooCommerce é um plugin Wordpress que habilita lojas WooCommerce à aceitarem [Dash](https://www.dash.org "Dash - Digital Cash") diretamente, sem a necessidade que um terceiro realize o processamento do pagamento.

* Utiliza BIP32 chave pública extendida (xpub) para geração de endereços.
* Requer acesso à instância [Insight-API-Dash](https://github.com/udjinm6/insight-api-dash).

### Requerimentos:

* Uma carteira Electrum-Dash para recebimento dos pagamentos
* WordPress 4.4.2+
* WooCommerce 2.5.2+
* PHP 5.5+ com as seguintes extenções:
  - gmp
  - bcmath
  - gd
  - mcrypt
  - openssl
  - curl
  - json

### Desenvolvedores: Costruindo o plugin

Instale o composer usando seu gerenciador de pacotes, então use ```composer install``` para buscar todas as dependências e colocá-las em 'vendor/'.

Se desejar, use 'zip' para empacotar o diretório em um arquivo zip com o mesmo nome:

    cd .. && zip -r dashpay-woocommerce.zip dashpay-woocommerce/

### Instalação e Ativação

Extraia o arquivo .zip e copie ou FTP o diretório dashpay-woocommerce para o diretório do WordPress 'plugins'. Ative o plugin no console do WordPress-admin.

Navegue para o WooCommerce -> Configurações -> Checkout. Clique na opção "Dash" na parte superior da página. Cole a sua chave xpub da Electrum-Dash na caixa "Dash BIP32 Extended Public Key" e clique em 'Salvar alterações' na parte inferior da página.

Se você vir uma mensagem informando que "O gateway de pagamento do Dash está operacional", você deve estar pronto para aceitar o pagamento no Dash.

Aqui está um vídeo do YouTube que demonstra o processo exato que eu descrevi acima:

<https://www.youtube.com/watch?v=HFzMPBY1rAQ>

### **ALTAMENTE RECOMENDADO (LEIA ESTA SEÇÃO)**

É altamente recomendável que você configure um trabalho cron para lidar com o processamento de pedidos em segundo plano. Não é tecnicamente necessário, mas irá pegar coisas como se um usuário fechasse seu navegador antes do pagamento ser processado.

Para trabalhos cron manuais, adicione esta linha ao seu crontab (substitua <seudominio.com> por seu próprio URL do site WordPress):

     * * * * * Curl -s http: // <seudominio.com> /wp-cron.php?doing_wp_cron> / dev / null 2> & 1

Para CPANEL, execute o comando abaixo cada minuto (substitua <seudominio.com> pelo seu URL do site WordPress):

     Curl -s http: // <seudominio.com> /wp-cron.php?doing_wp_cron> / dev / null 2> & 1

### Contribuições / Bugs / Problemas

Se você quiser contribuir com o projeto, envie uma solicitação de puxar para este repositório Github (por favor, garanta esse projeto e envie uma solicitação de puxar usando um ramo de recursos).

Se você acha que encontrou um bug, por favor, envie um problema a esse pagamento do Github (comece clicando na guia de problemas acima).

### Licença

DashPayments para WooCommerce é licenciado sob os termos da licença MIT. See http://opensource.org/licenses/MIT.
