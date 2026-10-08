# Segurança e privacidade

Resumo das proteções implementadas, organizado pelos requisitos do documento do projeto.

## Acesso e permissões

- As permissões por perfil e por área são verificadas no servidor (*policies* do Laravel) em todas
  as rotas do painel. Testes automatizados chamam as rotas diretamente para confirmar respostas 403.
- Não existem senhas nem usuários padrão no código. O primeiro Master é criado pelo terminal, com
  senha digitada de forma oculta ou definida por convite de uso único, ou pelo instalador web
  protegido por `SETUP_TOKEN`, que só funciona sem usuários cadastrados.
- Os convidados definem a própria senha (mínimo de 10 caracteres, com letras e números). O convite
  vale 72 horas e só pode ser usado uma vez.
- Recuperação de acesso: a mensagem é sempre a mesma, para não revelar quais e-mails existem.
- Login com limite de 5 tentativas por minuto por e-mail e IP. A sessão é regenerada ao entrar.
- Desativar um usuário encerra o acesso na requisição seguinte e preserva autoria e histórico.
- Registro de atividades: quem fez o quê, quando e de qual IP.

## Conteúdo não publicado

- As consultas públicas passam por um único serviço (`PublicActions`), que só lê versões aprovadas
  de ações não arquivadas e fora da lixeira.
- Rascunhos, observações de revisão e contatos marcados como internos nunca chegam às páginas
  públicas, o que é coberto por teste.
- Arquivos ficam em disco privado. A rota pública `/midia/...` só entrega arquivos em uso por conteúdo
  publicado; os demais respondem 404. No painel, o acesso exige autorização.
- Painel e prévias respondem com `Cache-Control: no-store` e `noindex`.

## Envio de arquivos

- Limites de quantidade e tamanho aplicados no servidor, com mensagem clara.
- O tipo é verificado pelo conteúdo (*magic bytes* e `getimagesize`), não pela extensão.
- Imagens: JPEG, PNG ou WebP, até 40 megapixels. Todas são reprocessadas, o que **remove metadados
  EXIF, inclusive a localização GPS**, e geram versões de 480, 960, 1600 e 2400 px sem ampliar nem
  deformar.
- PDFs: assinatura `%PDF-` e tipo conferidos; entregues com `Content-Security-Policy: sandbox`.

## Links externos (proteção contra SSRF)

Implementado em `app/Services/SafeFetcher.php`, seguindo as recomendações do
[OWASP SSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html):

- somente `http`/`https`, portas 80 e 443, sem usuário e senha na URL;
- resolução de DNS antes da conexão: **todos** os IPs precisam ser públicos (bloqueio de
  loopback, redes privadas, link-local, CGNAT, multicast, IPv6 local e endereços mapeados);
- conexão fixada no IP validado (`CURLOPT_RESOLVE`), contra troca de DNS durante a consulta;
- redirecionamentos seguidos manualmente (no máximo 3) e revalidados;
- tempo limite (3 s para conectar, 6 s no total) e tamanho máximo da resposta (1,5 MB para HTML);
- do HTML recebido, só se extraem textos curtos de metadados. Nenhum HTML externo é armazenado ou
  executado. A imagem sugerida só é baixada se a equipe escolher, e passa pelo mesmo processamento
  das fotos.

## Texto formatado e incorporações

- Descrições em Markdown, com HTML bruto escapado, links `javascript:`/`data:` removidos e imagens
  externas convertidas em texto.
- O YouTube usa o player oficial no domínio de privacidade avançada (`youtube-nocookie.com`), só
  após o clique. O Instagram usa o código de incorporação oficial, montado pelo sistema a partir do
  endereço validado e carregado só após o clique.
- *Content-Security-Policy* restritiva: scripts apenas do próprio site (e do Instagram, após o
  clique), quadros apenas do próprio site, YouTube e Instagram. Também são enviados
  `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options` e `Permissions-Policy`, além de HSTS
  quando houver HTTPS.

## Privacidade (LGPD)

A Lei nº 13.709/2018 exige medidas de segurança "aptas a proteger os dados pessoais de acessos não
autorizados" (art. 46) e limita o tratamento "ao mínimo necessário" (art. 6º, III). No sistema:

- as páginas públicas **não gravam cookies** e não usam rastreadores; as fontes são locais;
- a foto, o contato e a apresentação de cada integrante são opcionais, e o contato é interno por
  padrão;
- os metadados de localização das fotos são removidos;
- o conteúdo de terceiros só carrega com ação do visitante, com aviso;
- há uma página `/privacidade` com o aviso técnico, que deve ser complementada pela política
  oficial do órgão.

## Credenciais necessárias e onde ficam

| Credencial | Onde | Observação |
| --- | --- | --- |
| `APP_KEY` | `.env` | Gerada uma vez. Trocá-la invalida sessões e links de convite |
| Banco de dados | `.env` | Usuário com acesso só ao banco do portfólio |
| SMTP (e-mail) | `.env` | Conta de envio do domínio |
| `SETUP_TOKEN` | `.env` | Temporário: apague após instalar |

Não há chaves de API obrigatórias. A prévia do YouTube usa o oEmbed público, e o Instagram não exige
token para a incorporação usada.
