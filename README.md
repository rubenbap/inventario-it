# Inventário IT

Aplicação web para gerir o equipamento informático de várias empresas: portáteis, monitores, telemóveis, impressoras, servidores, NAS, linhas móveis e acessórios. Cada item pertence a uma empresa, tem histórico completo e pode ser atribuído a uma pessoa, a uma localização ou a outro equipamento.

A API é **compatível com o Snipe-IT**: as rotas, os campos e as respostas são iguais. Scripts e ferramentas feitos para o Snipe-IT (por exemplo o módulo PowerShell SnipeitPS) funcionam só a mudar o URL e o token.

| | |
| --- | --- |
| Backend | Laravel 12, PHP 8.3 |
| Base de dados | PostgreSQL 16 |
| Servidor | PHP-FPM + nginx num só contentor ([serversideup/php](https://serversideup.net/open-source/docker-php/)) |
| Instalação | Docker Compose |

## Índice

- [Funcionalidades](#funcionalidades)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Dados de demonstração](#dados-de-demonstração)
- [Permissões](#permissões)
- [API](#api)
- [Backups e restauro](#backups-e-restauro)
- [Atualizar](#atualizar)
- [Desenvolvimento](#desenvolvimento)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Segurança](#segurança)
- [Estado do projeto](#estado-do-projeto)

## Funcionalidades

- **Várias empresas.** Cada equipamento, acessório, utilizador e localização pertence a uma empresa. As asset tags levam o prefixo da empresa (`CNT-00042`), com numeração própria em cada uma.
- **Assets.** Marca, modelo, categoria, estado, n.º de série, compra, fornecedor e garantia. A categoria vem do modelo: escolhe-se o modelo e só falta o n.º de série.
- **Check-out e check-in** para uma pessoa, uma localização (sala, piso, bastidor) ou outro asset. Os estados funcionam como no Snipe-IT: `deployable`, `pending`, `undeployable` e `archived`.
- **Impressoras:** IP, hostname, laser ou jato de tinta, cor ou mono, multifunções, formato, contador de páginas, página de gestão e contrato.
- **Servidores e NAS:** nome e IP.
- **Linhas móveis:** número, tarifário, PIN, PUK, data de ativação, portabilidade e acessórios entregues. O PIN e o PUK ficam cifrados na base de dados. A linha fica associada ao telemóvel e acompanha-o quando ele muda de mãos.
- **Acessórios** com controlo de stock e alerta de stock mínimo.
- **Campos personalizados** agrupados em fieldsets, com validação (IP, MAC, e-mail, data, número, URL ou expressão regular), listas de opções e cifra opcional.
- **Histórico** de todas as criações, alterações, atribuições, devoluções, transferências entre empresas e consultas de PIN/PUK. O histórico nunca se altera nem se apaga.
- **Equipamentos por completar.** Um equipamento sem marca, com um modelo genérico (por exemplo "Monitor") ou sem n.º de série fica marcado como **por completar**. O painel mostra quantos são e a API devolve-os com `?incomplete=1`. A marca desaparece sozinha quando o que falta é preenchido.
- **Menu lateral com totais** em todas as secções (Assets, Impressoras, Servidores e NAS, Linhas móveis, Acessórios, Utilizadores e Empresas). Os números respeitam a empresa escolhida na barra de cima.
- **Painel** com totais, devoluções em atraso, stock baixo, garantias a terminar e atividade recente, com filtro por empresa.
- **Tokens da API** criados na própria aplicação, cada um **só de leitura** ou de **leitura e escrita**, e com validade (30 dias, 90 dias, 1 ano ou sem fim).
- **Backup diário** automático da base de dados.

## Instalação

Precisas de Docker com o plugin Compose. Os comandos abaixo assumem que estás na pasta do projeto.

**1. Criar o ficheiro de configuração**

```bash
cp .env.example .env
```

Abre o `.env` e muda, pelo menos, `DB_PASSWORD`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.

**2. Gerar a chave da aplicação**

```bash
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false app php artisan key:generate --show
```

Copia o valor que aparece (começa por `base64:`) para `APP_KEY` no `.env`.

> Guarda uma cópia da `APP_KEY` num sítio seguro, fora do servidor. É ela que cifra o PIN e o PUK das linhas móveis. Se a perderes, esses valores deixam de se poder ler.

**3. Arrancar**

```bash
docker compose up -d --build
```

No arranque, a aplicação cria as tabelas sozinha e faz cache da configuração, das rotas e das vistas.

**4. Criar os dados base e o administrador**

```bash
docker compose exec app php artisan db:seed
```

Isto cria os estados, as categorias, os campos personalizados das impressoras, servidores/NAS e linhas móveis, e o administrador com o `ADMIN_EMAIL` e a `ADMIN_PASSWORD` do `.env`. Se não tiveres definido `ADMIN_PASSWORD`, é gerada uma password e mostrada no terminal.

**5. Entrar**

Abre <http://localhost:8080>. Para usares outra porta, muda `APP_PORT` no `.env`.

**6. Fixar as versões das dependências** (recomendado, uma vez)

```bash
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false app cat composer.lock > composer.lock
git add composer.lock && git commit -m "Fixar dependências"
```

## Configuração

Todas as opções ficam no `.env`.

| Variável | Valor por defeito | Para quê |
| --- | --- | --- |
| `APP_NAME` | Inventário IT | Nome que aparece na aplicação |
| `APP_KEY` | vazio | Chave da aplicação. Obrigatória; cifra o PIN/PUK |
| `APP_URL` | `http://localhost:8080` | Endereço público da aplicação |
| `APP_PORT` | `8080` | Porta no servidor |
| `APP_TIMEZONE` | `Europe/Lisbon` | Fuso horário das datas |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `inventario` | Base de dados PostgreSQL |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_FIRST_NAME` | | Primeiro administrador, criado com `db:seed` |
| `API_THROTTLE_PER_MINUTE` | `120` | Pedidos por minuto por token na API |
| `ENFORCE_SAME_COMPANY_CHECKOUT` | `true` | Só permite atribuir um asset a pessoas da mesma empresa. Um admin pode forçar, mas tem de deixar uma nota |
| `SESSION_LIFETIME` | `480` | Minutos até a sessão expirar |
| `MAIL_MAILER` | `log` | Como se enviam os e-mails (links de nova password). `log` só os escreve no registo da aplicação; `smtp` envia-os mesmo |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` | `smtp.office365.com`, `587`, `smtp` | Servidor de e-mail. Com Microsoft 365, usa uma caixa de envio da empresa (com SMTP AUTH ligado no tenant) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | | Remetente dos e-mails |

## Dados de demonstração

Para experimentar com dados fictícios (3 empresas, portáteis, impressoras, servidores, NAS, telemóveis com linhas e acessórios):

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder
```

Fica disponível uma conta só de consulta:

| Conta | Password | Acesso |
| --- | --- | --- |
| `consulta@example.com` | `demo-consulta-2026` | Só consulta |

Não uses os dados de demonstração em produção.

## Permissões

Há dois tipos de utilizador: **sem login**, que só existem para lhes atribuir equipamentos, e **com login**, que entram no site. Quem tem login vê sempre todas as empresas e tem um de dois níveis:

| | Pode mexer | Só consulta |
| --- | --- | --- |
| Ver tudo, de todas as empresas | Sim | Sim |
| Criar, editar, apagar, check-out e check-in | Sim | Não |
| Ver PIN/PUK | Sim | Não |
| Importar e exportar | Sim | Não |
| Gerir empresas, estados, campos personalizados e logins | Sim | Não |
| Criar tokens da API | Sim | Não |

Na base de dados e na API, "Pode mexer" é o valor `admin` do campo `role` e "Só consulta" é `leitura`, como no Snipe-IT. A empresa escolhida na barra de cima é só um filtro, não um bloqueio.

O login bloqueia durante 15 minutos ao fim de 5 tentativas falhadas.

### Empresas

Os admins gerem as empresas na página **Empresas**:

- criar empresas, com nome, código (o prefixo das asset tags, 2 a 5 letras ou números) e NIF, e opcionalmente a primeira localização;
- editar ou desativar uma empresa. Uma empresa desativada deixa de aparecer nos formulários, mas os dados ficam;
- apagar uma empresa, só se não tiver equipamentos, linhas, acessórios nem pessoas.

A lista mostra quantos assets, impressoras, servidores/NAS, linhas, acessórios, pessoas e logins tem cada empresa. Na barra de cima, o seletor de empresa filtra a aplicação inteira, e a escolha fica guardada na sessão.

### Importar e exportar

Na página **Importar / Exportar**:

- **Importar** assets, impressoras, servidores/NAS, linhas móveis, acessórios ou utilizadores, a partir de um ficheiro `.xlsx` ou `.csv`, ou colando as linhas copiadas do Excel.
  1. O tipo de ficheiro e as colunas são reconhecidos automaticamente, mesmo que tenhas escolhido outra opção (por exemplo, um ficheiro do Snipe-IT colado em "Linhas móveis" passa para "Snipe-IT"). Podes corrigir as colunas antes de continuar.
  2. A pré-visualização mostra, linha a linha, o que é novo, o que atualiza um registo existente (pela tag, S/N, IMEI, n.º de telefone ou e-mail) e o que tem erros. Nada é gravado até confirmares.
  3. As linhas com erro ficam de fora. As linhas sem nada que identifique o registo (sem tag, S/N, modelo nem nome; nas linhas móveis, sem número) são ignoradas: é o caso das linhas a mais que vêm quando se copia do Excel.
  4. Com a opção "Criar automaticamente" ligada, o que falta é criado durante a importação:
     - **empresas** (só admins), com o código tirado do nome: "Empresa Nova Lda" fica `ENL`, com tags `ENL-00001`…;
     - **categorias**, com o nome que vem no ficheiro (Switches, Tablets…);
     - **marcas e modelos**. Se a marca faltar, é deduzida pelo modelo (ProBook dá HP, Latitude dá Dell, ThinkPad dá Lenovo, MacBook dá Apple, Galaxy dá Samsung…). Se não for possível, o equipamento fica com a marca "Sem marca";
     - **pessoas**, só com o nome ou só com o e-mail, sem login. Quando só há e-mail, o nome é tirado dele (`pedro.lima@…` dá "Pedro Lima"). A pessoa fica na empresa do equipamento;
     - **localizações**.
  5. **Atribuição:** se a linha indicar uma pessoa, o equipamento é atribuído a essa pessoa, quer ela já exista quer tenha sido criada nessa importação. Há duas exceções, e ambas aparecem como aviso na pré-visualização:
     - se o estado não permitir atribuir (Avariado, Abatido…), a pessoa é criada mas o equipamento não lhe fica atribuído;
     - se o equipamento já estiver atribuído a outra pessoa, mantém-se com ela.
  6. Equipamentos sem marca, com modelo genérico ou sem S/N entram na mesma, mas ficam marcados como **por completar**. A pré-visualização avisa em cada linha.
  7. S/N como `N/A`, `-`, `0`, `s/n` ou `desconhecido` contam como vazios e por isso não aparecem como "repetido no ficheiro".
  8. Cada importação pode ser **anulada** nas primeiras 24 horas. Tudo volta ao estado anterior, incluindo as empresas, categorias, pessoas, modelos e localizações que ela criou, desde que não tenham ficado em uso.
- **Linhas móveis:** o Excel atual entra tal como está. O PIN/PUK é separado, o telemóvel é reconhecido pelo nome (por exemplo, "SAMSUNG A15 - NOVO" passa a Samsung Galaxy A15) e criado pelo IMEI, com verificação Luhn. As notas também são lidas: "Capa + Película - NOVO 13/01/2025" e "Portado da … - 01/08/24".
- **Ficheiro do Snipe-IT:** escolhe "Snipe-IT (assets)" e carrega o CSV exportado do Snipe-IT tal como sai (Assets → Exportar, ou Relatórios → Relatório personalizado).
  - As colunas em inglês são reconhecidas sozinhas: Asset Tag, Model Name, Manufacturer, Category, Serial, Status, Full Name, Location…
  - Cada linha vai para a secção certa pela Category: Laptops/Desktops/Monitors para Assets, Mobile Phones para Telemóvel, Printers para Impressoras, Servers/NAS para Servidores e NAS.
  - Os estados do Snipe-IT são convertidos para os da aplicação ("Checked out", "Deployed" e "Ready to Deploy" passam a Pronto). Os que não têm correspondência ficam Pronto, com aviso.
  - A pessoa a quem o equipamento é atribuído vem de **Full Name**, **Checked Out To** ou **Email**. Se essas colunas estiverem vazias e o **Asset Name** for um nome de pessoa, essa pessoa é criada e fica com o equipamento. Conta como nome de pessoa: 2 a 5 palavras, com qualquer combinação de maiúsculas e minúsculas, sem números nem hífenes, e sem palavras como "Portátil", "Reserva", "Sala" ou "Stock". Na pré-visualização aparece uma linha a dizer de onde veio o nome.
  - As categorias que não são reconhecidas são criadas com o mesmo nome. "PC" vai para Desktop e "Computadores Portáteis" para Portátil.
  - A página mostra o layout completo, coluna a coluna, e tem o cabeçalho para descarregar.
  - O ficheiro é detetado em qualquer opção quando tem a coluna Asset Tag e pelo menos mais duas destas: Model Name, Model, Manufacturer, Category ou Serial.
- **Exportar** qualquer secção, e o histórico, para Excel ou CSV, só da empresa escolhida na barra de cima. Os admins podem incluir o PIN/PUK das linhas; essa exportação fica registada no histórico.

### Gerir logins

Os admins gerem os logins na página **Acessos** (menu lateral):

- criar logins "Pode mexer" ou "Só consulta";
- mudar o nível de acesso ou a password;
- enviar um link para a pessoa definir uma nova password;
- tirar o acesso.

Ao criar um login, a password é opcional. Se ficar em branco, a pessoa recebe um e-mail com um link para a definir ela própria.

### Recuperar a password

No ecrã de login há a ligação **Esqueci-me da password**:

1. A pessoa escreve o e-mail com que entra.
2. Se esse e-mail tiver login ativo, recebe um link para definir uma nova password. A página mostra sempre a mesma mensagem, para não revelar quem tem conta.
3. O link é válido durante 60 minutos e só pode ser usado uma vez.
4. Ao definir a nova password (mínimo 10 caracteres), os tokens da API dessa pessoa deixam de funcionar. A alteração fica no histórico.

Os pedidos estão limitados (5 em cada 10 minutos por endereço IP), para evitar abusos.

Para isto funcionar é preciso configurar o envio de e-mail no `.env` (ver [Configuração](#configuração)). Enquanto não estiver configurado, o link fica escrito no registo da aplicação (`docker compose logs app`). Também podes gerar um link à mão e enviá-lo à pessoa:

```bash
docker compose exec app php artisan inventario:link-password nome@empresa.pt
```

Podem existir vários admins, e várias pessoas podem estar ligadas ao mesmo tempo, cada uma com a sua sessão. Quando dois admins tentam atribuir o mesmo equipamento ao mesmo tempo, a base de dados bloqueia o registo: só a primeira atribuição passa, e a segunda recebe o aviso de que o equipamento já está atribuído.

Regras de segurança:

- Tem de existir sempre pelo menos um login ativo que possa mexer.
- Ninguém pode tirar a si próprio o "Pode mexer" nem desativar o próprio login.
- Quem perde o login deixa de ter acesso logo no pedido seguinte, e os seus tokens da API são revogados.

Para criar um login que pode mexer, ou repor a password de um, a partir da linha de comandos:

```bash
docker compose exec app php artisan inventario:admin nome@empresa.pt --name="Nome"
```

## API

### Autenticação

1. Entra na aplicação e abre **Tokens da API** (no menu da conta, em cima à direita).
2. Dá-lhe um nome, escolhe o acesso e a validade, e gera-o.
3. Copia-o logo: só é mostrado uma vez.

| Acesso | O que pode fazer | Para quê |
| --- | --- | --- |
| **Só leitura** | Consultar tudo (equipamentos, pessoas, linhas, histórico, resumo por empresa). O PIN/PUK aparece como `********`. Qualquer pedido que altere dados responde `403 Este token é só de leitura.` | Power BI, relatórios, painéis |
| **Leitura e escrita** | Tudo o que quem o criou pode fazer, incluindo ver PIN/PUK | Scripts que criam ou atualizam dados (por exemplo, a partir do Intune) |

Dá a cada programa o seu token, com o mínimo de acesso de que precisa. Um token expirado deixa de funcionar (responde `401`) e aparece como expirado na lista, onde o podes apagar. Revogar um token corta-lhe o acesso logo.
3. Envia-o em todos os pedidos:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Um token nunca tem mais acesso do que quem o criou: se essa pessoa passar a "Só consulta" ou perder o login, o token deixa de poder escrever ou é revogado.

### Convenções

Iguais às do Snipe-IT:

- **Base:** `/api/v1`
- **Listas:** aceitam `limit` (50 por defeito, máximo 500), `offset`, `search`, `sort` e `order` (`asc` ou `desc`), e respondem com `{"total": N, "rows": [...]}`.
- **Escritas:** respondem com `{"status": "success", "messages": "...", "payload": {...}}`.
- **Erros:** `{"status": "error", "messages": {"campo": ["erro"]}, "payload": null}`. Um erro de validação vem com HTTP 200, como no Snipe-IT; os outros usam 401, 403 ou 404.
- **Relações:** vêm como `{"id": 1, "name": "..."}`.
- **Datas:** vêm como `{"date": "2026-11-20", "formatted": "20/11/2026"}`.
- **Limite:** 120 pedidos por minuto por token.

### Rotas

| Método e rota | Para quê |
| --- | --- |
| `GET` `POST` `/hardware` | Listar e criar assets |
| `GET` `PUT` `PATCH` `DELETE` `/hardware/{id}` | Ver, editar e apagar um asset |
| `GET` `/hardware/bytag/{tag}` | Procurar por asset tag |
| `GET` `/hardware/byserial/{serial}` | Procurar por n.º de série ou IMEI |
| `POST` `/hardware/{id}/checkout` | Atribuir a uma pessoa, localização ou asset |
| `POST` `/hardware/{id}/checkin` | Devolver |
| `POST` `/hardware/{id}/restore` | Recuperar um asset apagado |
| `GET` `POST` `/accessories`, `GET` `PUT` `PATCH` `DELETE` `/accessories/{id}` | Acessórios |
| `GET` `/accessories/{id}/checkedout` | Unidades entregues (o `id` de cada uma serve para o check-in) |
| `POST` `/accessories/{id}/checkout` | Entregar unidades (`assigned_to`, `checkout_qty`, `note`) |
| `POST` `/accessories/{checkoutId}/checkin` | Receber uma unidade |
| `GET` `POST` `/users`, `GET` `PUT` `PATCH` `DELETE` `/users/{id}` | Utilizadores |
| `GET` `/users/me` | O utilizador do token |
| `GET` `/users/{id}/assets`, `/users/{id}/accessories` | O que um utilizador tem atribuído |
| CRUD `/companies`, `/locations`, `/models`, `/manufacturers`, `/categories`, `/statuslabels`, `/suppliers`, `/departments` | Tabelas de apoio |
| CRUD `/fields`, `/fieldsets` | Campos personalizados e fieldsets |
| `POST` `/fields/{id}/associate`, `/fields/{id}/disassociate` | Pôr ou tirar um campo de um fieldset |
| `GET` `/fieldsets/{id}/fields` | Campos de um fieldset |
| `GET` `/reports/activity` | Histórico |
| `GET` `/reports/summary` | Totais por empresa: assets (e quantos estão atribuídos), impressoras, servidores e NAS, linhas, acessórios, pessoas e equipamentos por completar. Aceita `?company_id=`. Não existe no Snipe-IT |
| `GET` `/hardware/{id}/pin` | Só o PIN e o PUK de uma linha, para quem pode mexer. Fica no histórico. Devolve 404 se o asset não tiver PIN/PUK. Não existe no Snipe-IT |

### Filtros de `/hardware`

| Parâmetro | Exemplo |
| --- | --- |
| `company_id`, `model_id`, `category_id`, `manufacturer_id`, `status_id`, `location_id`, `supplier_id` | `?category_id=6` |
| `status` | `RTD` (prontos), `Deployed` (atribuídos), `Pending`, `Undeployable`, `Archived`, `Deleted` |
| `assigned_to` e `assigned_type` | `?assigned_to=3&assigned_type=user` |
| `incomplete` | `?incomplete=1`: só os equipamentos por completar (sem marca, com modelo genérico ou sem S/N). Cada asset traz `missing_fields`, com `manufacturer`, `model` e/ou `serial`. Não existe no Snipe-IT; as ferramentas feitas para ele ignoram-no |
| `search` | Procura na tag, S/N, nome, n.º de encomenda, notas, modelo, marca e categoria. Em PostgreSQL procura também nos campos personalizados (IP, n.º de telefone…) |
| `sort` | `asset_tag`, `serial`, `name`, `purchase_date`, `purchase_cost`, `expected_checkin`, `last_checkout`, `warranty_expires`, `created_at` |

Por defeito, os assets arquivados não aparecem. Para os ver, usa `status=Archived`.

### Exemplos

```bash
TOKEN="cola-aqui-o-token"
API="http://localhost:8080/api/v1"
H=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")
```

Listar e procurar:

```bash
curl "${H[@]}" "$API/hardware?limit=20&search=thinkpad"
curl "${H[@]}" "$API/hardware?status=RTD&company_id=1"
curl "${H[@]}" "$API/hardware/bytag/CNT-00001"
curl "${H[@]}" "$API/hardware/byserial/PF4X1A2B"
```

Modelos de uma marca (o que o formulário de criação usa):

```bash
curl "${H[@]}" "$API/models?manufacturer_id=1"
```

Criar um asset. Sem `asset_tag`, a tag é gerada com o prefixo da empresa:

```bash
curl "${H[@]}" -X POST "$API/hardware" \
  -d '{"company_id": 1, "model_id": 1, "status_id": 1, "serial": "ABC123", "purchase_date": "2026-11-20", "warranty_months": 36}'
```

Criar e atribuir logo, como no Snipe-IT:

```bash
curl "${H[@]}" -X POST "$API/hardware" \
  -d '{"company_id": 1, "model_id": 1, "status_id": 1, "serial": "ABC124", "assigned_user": 3}'
```

Check-out e check-in:

```bash
curl "${H[@]}" -X POST "$API/hardware/5/checkout" \
  -d '{"checkout_to_type": "user", "assigned_user": 3, "expected_checkin": "2027-01-31", "note": "Portátil novo"}'

curl "${H[@]}" -X POST "$API/hardware/7/checkout" \
  -d '{"checkout_to_type": "location", "assigned_location": 2}'

curl "${H[@]}" -X POST "$API/hardware/5/checkin" -d '{"note": "Devolvido", "status_id": 1}'
```

Mudar um asset de empresa (tem de estar devolvido):

```bash
curl "${H[@]}" -X PATCH "$API/hardware/5" -d '{"company_id": 2, "note": "Passou para a Serviços Sul"}'
```

Histórico de um asset:

```bash
curl "${H[@]}" "$API/reports/activity?item_type=asset&item_id=5"
```

Totais por empresa (por exemplo para o Power BI) e PIN/PUK de uma linha:

```bash
curl "${H[@]}" "$API/reports/summary"
curl "${H[@]}" "$API/hardware/12/pin"
```

### Campos personalizados

Impressoras, servidores, NAS e linhas móveis são assets normais em `/hardware`, separados pela categoria do modelo. Os campos próprios de cada tipo são campos personalizados, como no Snipe-IT:

- na resposta, aparecem em `custom_fields`, pelo nome do campo;
- para os escrever, usa a chave `_snipeit_<nome>_<id>`.

Para ver as chaves da tua instalação:

```bash
curl "${H[@]}" "$API/fields"
```

Numa instalação nova, depois do `db:seed`, as chaves são estas:

| Fieldset | Campos |
| --- | --- |
| Impressora | `_snipeit_endereco_ip_1`, `_snipeit_hostname_2`, `_snipeit_tecnologia_3`, `_snipeit_cor_4`, `_snipeit_multifuncoes_5`, `_snipeit_formato_maximo_6`, `_snipeit_contador_de_paginas_7`, `_snipeit_pagina_de_gestao_8`, `_snipeit_tipo_de_contrato_9`, `_snipeit_fim_do_contrato_10` |
| Servidor / NAS | `_snipeit_hostname_2`, `_snipeit_endereco_ip_1` |
| Linha móvel | `_snipeit_numero_de_telefone_11`, `_snipeit_tarifario_12`, `_snipeit_pin_13`, `_snipeit_puk_14`, `_snipeit_data_de_ativacao_15`, `_snipeit_portado_de_16`, `_snipeit_acessorios_entregues_17`, `_snipeit_data_dos_acessorios_18` |

Exemplo: registar uma impressora e pô-la numa sala. Os `model_id` dos exemplos são os dos dados de demonstração.

```bash
curl "${H[@]}" -X POST "$API/hardware" -d '{
  "company_id": 1, "model_id": 8, "status_id": 1, "serial": "PHBQK99999",
  "_snipeit_endereco_ip_1": "10.0.1.31",
  "_snipeit_hostname_2": "PRT-PORTO02",
  "_snipeit_cor_4": "Mono",
  "assigned_location": 1
}'
```

### Linhas móveis

- **Criar uma linha:** é um asset de um modelo da categoria "Linha móvel". O número tem de ter 9 dígitos e começar por 91, 92, 93 ou 96.
- **PIN e PUK:** ficam cifrados. Nas listas aparecem sempre como `********`. Só se veem no detalhe (`GET /hardware/{id}`, `bytag`, `byserial`) ou em `GET /hardware/{id}/pin`, e apenas para quem pode mexer. Cada consulta fica registada no histórico como `view encrypted`.
- **Ligar a linha ao telemóvel:** faz check-out da linha para o telemóvel. Depois, o telemóvel faz check-out para a pessoa, e a linha vai com ele.

```bash
curl "${H[@]}" -X POST "$API/hardware" -d '{
  "company_id": 1, "model_id": 13, "status_id": 1, "name": "912 345 610",
  "_snipeit_numero_de_telefone_11": "912345610",
  "_snipeit_tarifario_12": "Biz Standard",
  "_snipeit_pin_13": "1234",
  "_snipeit_puk_14": "12345678"
}'

curl "${H[@]}" -X POST "$API/hardware/{id_da_linha}/checkout" \
  -d '{"checkout_to_type": "asset", "assigned_asset": {id_do_telemovel}}'
```

## Backups e restauro

O serviço `backup` faz um `pg_dump` por dia para a pasta `./backups` (`inventario-AAAA-MM-DD.sql.gz`) e apaga os que têm mais de 14 dias.

Para um backup completo, guarda também:

- o ficheiro `.env`, sobretudo a `APP_KEY`;
- o volume `storage`, com as imagens enviadas.

Restaurar um backup:

```bash
gunzip -c backups/inventario-2026-11-20.sql.gz | docker compose exec -T db psql -U inventario -d inventario
```

Fazer um backup agora:

```bash
docker compose exec db pg_dump -U inventario inventario | gzip > backups/manual-$(date +%F-%H%M).sql.gz
```

## Atualizar

```bash
git pull
docker compose up -d --build
```

As migrations novas correm sozinhas no arranque.

## Desenvolvimento

Fora do Docker, com PHP 8.3 e Composer:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

Os testes usam SQLite em memória e cobrem:

- formato das respostas da API;
- geração de asset tags por empresa;
- erros de validação;
- check-out e check-in;
- filtro por empresa;
- cifra do PIN/PUK;
- permissões;
- ligação entre linha e telemóvel.

Em desenvolvimento, a aplicação avisa se alguma listagem fizer consultas a mais (N+1).

O GitHub Actions corre os testes a cada push (`.github/workflows/tests.yml`).

## Estrutura do projeto

```
app/
  Http/
    Controllers/Api/    API compatível com o Snipe-IT
    Controllers/Web/    Login, painel e tokens
    Middleware/         Permissões por perfil
    Support/            Formato das respostas
    Transformers/       Conversão dos modelos para JSON
  Models/               Modelos Eloquent e filtro por empresa
  Services/             Check-out/check-in, campos personalizados, histórico
  Support/              Tipos de destino (user, location, asset)
config/inventario.php   Opções da aplicação
database/
  migrations/           Tabelas e índices
  seeders/              Dados base e de demonstração
resources/views/        Páginas
routes/                 Rotas da API e da web
tests/Feature/          Testes da API
docker-compose.yml      App, PostgreSQL e backups
Dockerfile              Imagem de produção
```

### Base de dados

| Tabela | Conteúdo |
| --- | --- |
| `companies` | Empresas e contador das asset tags |
| `company_user` | Empresas a que cada utilizador com login tem acesso |
| `users` | Pessoas, com e sem login |
| `assets` | Todos os equipamentos com n.º de série, incluindo impressoras, servidores, NAS, telemóveis e linhas. Os valores dos campos personalizados ficam na coluna JSON `custom_values` |
| `models`, `manufacturers`, `categories` | Modelos, marcas e categorias |
| `status_labels` | Estados |
| `locations`, `departments`, `suppliers` | Localizações, departamentos e fornecedores |
| `custom_fields`, `custom_fieldsets` | Campos personalizados e respetivos grupos |
| `accessories`, `accessory_checkouts` | Acessórios e unidades entregues |
| `action_logs` | Histórico |
| `personal_access_tokens` | Tokens da API |

## Segurança

- O `.env` não vai para o Git. Guarda a `APP_KEY` à parte: sem ela, o PIN/PUK não se recupera.
- As passwords ficam guardadas com bcrypt. Os tokens da API ficam guardados como hash.
- O filtro por empresa é aplicado no servidor e não só na interface.
- Não guardes passwords de servidores nem de equipamentos na aplicação. Usa um gestor de passwords.
- Em produção, põe a aplicação atrás de HTTPS (por exemplo Nginx Proxy Manager, Traefik ou Caddy).

## Estado do projeto

- [x] Instalação em Docker com backups diários
- [x] Base de dados completa, com índices de pesquisa
- [x] API compatível com o Snipe-IT
- [x] Login, painel e tokens da API (só leitura ou leitura e escrita, com validade)
- [x] Gestão de logins: vários logins em simultâneo, com dois níveis (Pode mexer e Só consulta)
- [x] Gestão de empresas e seletor de empresa na barra de cima
- [ ] Ecrãs de gestão: assets com formulário inteligente, impressoras, servidores e NAS, linhas móveis, acessórios, utilizadores
- [x] Importação (Excel das linhas móveis, CSV/Excel genérico e ficheiros do Snipe-IT) com pré-visualização e anular, que deteta o tipo de ficheiro e cria empresas, categorias e pessoas
- [x] Equipamentos marcados como por completar (no painel e na API)
- [ ] Ecrã para completar ou editar um equipamento (já existe na demonstração)
- [x] Exportações para CSV e Excel
- [x] Recuperar a password por e-mail e convite para novos logins
- [ ] Login com Microsoft (Entra ID)

## Licença

Uso privado. Todos os direitos reservados.
