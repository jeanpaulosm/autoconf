<?php

namespace Autoconf\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class RegisterService
{
    protected $client;
    protected $cookieJar;

    public function __construct(Client $client = null)
    {
        $this->cookieJar = new CookieJar();
        $this->client = $client ?: new Client([
            'cookies' => $this->cookieJar,
            'allow_redirects' => [
                'track_redirects' => true
            ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
            ]
        ]);
    }

    public function authenticate($email, $password)
    {
        try {
            // Primeiro GET para capturar o token de autenticação
            $response = $this->client->get('https://app.autoconf.com.br');
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);
            $token = $crawler->filter('input[name="_token"]')->attr('value');

            // Segundo POST para autenticação
            $response = $this->client->post('https://app.autoconf.com.br/login', [
                'form_params' => [
                    '_token' => $token,
                    'email' => $email,
                    'senha' => $password
                ]
            ]);

            // Verificar se o login foi bem-sucedido
            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            $currentUrl = end($redirectHistory);
            if ($currentUrl === 'https://app.autoconf.com.br/dashboard') {
                return 'Sucesso no processo de autenticação';
            } else {
                $html = (string) $response->getBody();
                if (preg_match("/alert\('danger',\s*'([^']+)'\s*,\s*'topRight'\);/", $html, $matches)) {
                    return 'Erro: ' . $matches[1];
                }
            }
        } catch (RequestException $e) {
            return 'Erro ao tentar autenticar: ' . $e->getMessage();
        }

        return 'Falha na autenticação';
    }

    public function openAttendanceModal()
    {
        try {
            $response = $this->client->get('https://app.autoconf.com.br/lead/atendimento/create');
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);
            $token = $crawler->filter('input[name="_token"]')->attr('value');
            return $token;
        } catch (RequestException $e) {
            return 'Erro ao tentar abrir o modal de atendimento: ' . $e->getMessage();
        }
    }

    public function registerAttendance($clienteId, $nome, $celular, $mediumId = 1, $contentId = null, $statusId = 1)
    {
        try {
            $token = $this->openAttendanceModal();
            if (strpos($token, 'Erro') !== false) {
                return $token;
            }

            $response = $this->client->post('https://app.autoconf.com.br/lead/atendimento/store', [
                'form_params' => [
                    '_token' => $token,
                    'cliente_id' => $clienteId,
                    'nome' => $nome,
                    'celular' => $celular,
                    'medium_id' => $mediumId,
                    'content_id' => $contentId,
                    'status_id' => $statusId
                ]
            ]);

            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            $currentUrl = end($redirectHistory);
            if (preg_match("/https:\/\/app\.autoconf\.com\.br\/lead\/atendimento\/(\d+)\/edit/", $currentUrl, $matches)) {
                return [
                    'status' => 'sucesso',
                    'atendimento_id' => $matches[1]
                ];
            } else {
                return 'Houve algum erro no registro do atendimento';
            }
        } catch (RequestException $e) {
            return 'Erro ao tentar registrar atendimento: ' . $e->getMessage();
        }
    }
}
