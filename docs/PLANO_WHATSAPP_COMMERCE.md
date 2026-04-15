# Plano de Desenvolvimento — WhatsApp Commerce AWA Motos

> **Versão:** 2.2
> **Data:** 2026-04-13
> **Autor:** Jess / GrupoAwamotos
> **Objetivo:** Integrar Chat + WhatsApp + IA para vender pelo WhatsApp (estilo Suri Shop) usando stack open-source

---

## Visão Geral

Criar um canal de vendas completo pelo WhatsApp, onde o cliente pode navegar o catálogo,
buscar peças por compatibilidade (Fitment), montar carrinho, pagar e acompanhar pedidos —
tudo sem sair do WhatsApp. Com transferência para atendente humano quando necessário.

### Infraestrutura do Servidor

O VPS atual comporta os containers adicionais sem upgrade imediato:

| Recurso | Disponível | Necessário (estimado) |
|---|---|---|
| RAM | 16 GB (7 GB livres) | ~3 GB para 3 containers |
| CPU | 4 vCores | ~1 vCore adicional |
| Disco | 193 GB (121 GB livres) | ~5 GB |
| Docker | 28.2.2 ✅ já instalado | — |

> ⚠️ Monitorar uso de RAM após subir os containers. Se uso passar de 90%, considerar upgrade de plano
> ou apontar N8N para um VPS separado.

### Divisão de Responsabilidades (Typebot vs N8N)

| Ferramenta | Função | Não usar para |
|---|---|---|
| **Typebot** | Fluxos conversacionais: menus, coleta de dados, catálogo interativo, carrinho, formulários B2B | Automações assíncronas, agendamentos, integrações complexas |
| **N8N** | Automações assíncronas: crons, webhooks de pedido, alertas, sync ERP, campanhas em massa, IA | Conversação em tempo real com cliente |
| **Evolution API** | Camada de transporte WhatsApp: envio/recebimento de mensagens, mídia, QR Code | Business logic |
| **Chatwoot** | Atendimento humano: inbox unificado, equipes, histórico, handoff do bot | Chatbot automatizado |

### Ambientes

| Ambiente | Número WhatsApp | URL Bot | Uso |
|---|---|---|---|
| **Staging** | Número pessoal/teste | `bot-staging.awamotos.com` | Desenvolvimento e QA |
| **Produção** | Número oficial AWA | `bot.awamotos.com` | Clientes reais |

> Nunca testar fluxos novos diretamente no número de produção.

### Stack de Tecnologia

| Componente | Ferramenta | Status |
|---|---|---|
| E-commerce | Magento 2.4.8 | ✅ Produção |
| Chat web (site) | Chatwoot (GrupoAwamotos_Chatwoot) | ✅ Instalado |
| Bot triagem | Typebot (menu 6 opções) | ✅ Funcionando |
| WhatsApp API | Evolution API (self-hosted) | ✅ Instalado |
| Chatbot visual | Typebot (self-hosted) | ✅ Instalado |
| Automação/Orquestração | N8N (self-hosted) | ✅ Instalado |
| IA Generativa | Groq (Llama 3.3 70B) | ✅ Funcionando |
| Carrinho abandonado | GrupoAwamotos_AbandonedCart | ✅ Produção (e-mail) |
| Sugestões recompra | GrupoAwamotos_SmartSuggestions | ✅ Produção + WhatsApp |
| Fitment (peça x moto) | GrupoAwamotos_Fitment | ✅ Produção |
| B2B | GrupoAwamotos_B2B | ✅ Produção |
| ERP | GrupoAwamotos_ERPIntegration | ✅ Produção |

### Plano de Contingência

| Falha | Impacto | Mitigação |
|---|---|---|
| Evolution API offline | Chatbot e notificações param | Health check N8N a cada 5min + alerta Telegram ao admin |
| N8N offline | Crons e automações param | Systemd restart automático + alertas |
| Número WhatsApp banido (Baileys) | Canal cai completamente | Número de backup configurado + migração para Cloud API |
| IA indisponível (Groq/OpenAI) | IA não responde | Fallback: transferir para humano (Chatwoot) |
| Magento REST API lenta | Typebot timeout | Cache de catálogo (Redis) nas APIs do WhatsAppCommerce |

---

## Guia Operacional por Perfil

> Esta seção é o manual de uso do WhatsApp Commerce AWA Motos para cada perfil de usuário.
> Consulte a seção correspondente ao seu papel na operação.

---

### 📋 Guia do Administrador (TI)

O administrador é responsável pela saúde do sistema, configuração das ferramentas e gestão de acessos.

#### Painel de Controle — Acessos Rápidos

| Ferramenta | URL | Função |
|---|---|---|
| Magento Admin | `https://awamotos.com/admin` | Painel da loja |
| Chatwoot | `https://chat.awamotos.com` | Atendimento humano |
| N8N | `https://n8n.awamotos.com` | Automações e workflows |
| Typebot Builder | `http://127.0.0.1:3001` (via SSH tunnel) | Editor do chatbot |
| Evolution API | `https://wpp.awamotos.com` | Gestão do WhatsApp |

#### Checklist Diário (5 minutos)

1. **Verificar status do WhatsApp:**
   - Acessar `https://wpp.awamotos.com/instance/connectionState/awamotos`
   - Status esperado: `open` ✅
   - Se `close`: reconectar via QR Code no painel Evolution API
2. **Verificar saúde dos containers:**
   ```bash
   docker ps --format "table {{.Names}}\t{{.Status}}" | grep awa-
   ```
   - Todos devem mostrar status `Up` e `(healthy)` quando disponível
3. **Verificar fila de mensagens pendentes:**
   - Magento Admin > Smart Suggestions > WhatsApp Queue
   - Se fila travada (> 50 pendentes): verificar logs
4. **Verificar logs de erro:**
   ```bash
   tail -20 var/log/whatsapp_commerce.log
   tail -20 var/log/erp_integration.log
   grep "ERROR" var/log/system.log | tail -10
   ```

#### Checklist Semanal (15 minutos)

1. **Uso de recursos do servidor:**
   ```bash
   free -h          # RAM (alerta se livre < 1 GB)
   df -h /          # Disco (alerta se uso > 85%)
   docker stats --no-stream | grep awa-
   ```
2. **Backup dos volumes Docker:**
   ```bash
   ls -lh /home/user/backups/docker-volumes/
   ```
3. **Atualizar containers (se houver release):**
   ```bash
   cd /home/user/htdocs/srv1113343.hstgr.cloud/infra/n8n
   docker compose pull && docker compose up -d
   ```
4. **Revisar métricas do Chatwoot:**
   - Chatwoot > Reports > Conversations > Últimos 7 dias
   - Verificar: tempo médio de resposta, conversas sem resolução, CSAT

#### Configurações Importantes no Magento Admin

| Caminho | O que faz | Valor padrão |
|---|---|---|
| Stores > Config > AWA > WhatsApp Commerce > Enable | Liga/desliga todo o canal WhatsApp | Sim |
| Stores > Config > AWA > WhatsApp Commerce > Opt-in Default | Opt-in padrão para novos clientes | Não (LGPD) |
| Stores > Config > AWA > Smart Suggestions > WhatsApp Provider | Provider de envio (evolution/meta/twilio) | evolution |
| Stores > Config > AWA > Smart Suggestions > Auto Send WhatsApp | Enviar sugestões RFM automaticamente | Sim |
| Stores > Config > AWA > Abandoned Cart > WhatsApp Enable | Carrinho abandonado via WhatsApp | Não (ativar na Fase 3) |
| Stores > Config > AWA > Abandoned Cart > Wave 1 Delay | Tempo até primeira mensagem | 1 hora |

#### Gestão de Equipes e Acessos

**Chatwoot — Criar novo atendente:**
1. Chatwoot > Settings > Agents > Add Agent
2. Preencher: nome, e-mail, e role (Agent ou Admin)
3. Atribuir às equipes: Vendas, Suporte, B2B
4. Atribuir inbox: "WhatsApp AWA" + "AWA Motos" (web)

**Chatwoot — Configurar horário de atendimento:**
1. Chatwoot > Settings > Business Hours
2. Definir: Seg-Sex 08:00-18:00, Sáb 08:00-12:00
3. Mensagem fora do horário: "Nosso atendimento funciona de segunda a sexta, das 8h às 18h. Deixe sua mensagem que respondemos no próximo dia útil! 😊"

**N8N — Verificar workflows ativos:**
1. Acessar `https://n8n.awamotos.com`
2. Confirmar workflows essenciais com status `Active`:
   - ⚡ WhatsApp: Notificação de Pedido Novo
   - 🤖 IA: Assistente AWA
   - 🔍 Chatwoot: Identificação de Cliente
   - 📅 Follow-up Pós-Compra

#### Procedimento de Emergência — WhatsApp Offline

```
1. Verificar container:     docker ps | grep awa-evolution
   └─ Se parado:            docker start awa-evolution
   └─ Se erro:              docker logs awa-evolution --tail 50

2. Verificar conexão:       curl -s https://wpp.awamotos.com/instance/connectionState/awamotos
   └─ Se "close":           Reconectar via QR Code no painel Evolution
   └─ Se timeout:           Verificar Nginx e rede

3. Notificar equipe:        Enviar mensagem no grupo interno:
                            "⚠️ WhatsApp temporariamente indisponível.
                             Atendimento apenas pelo site e telefone."

4. Após resolver:           Testar envio de mensagem de teste
                            Confirmar que bot está respondendo
```

#### Procedimento de Emergência — Ban do Número (Baileys)

```
1. IMEDIATAMENTE:
   - Ativar número de backup na Evolution API
   - Atualizar integração no Typebot para novo número
   - Notificar equipe

2. EM ATÉ 24H:
   - Iniciar processo de migração para Cloud API Oficial (Meta)
   - Solicitar verificação no Meta Business Suite
   - Cadastrar templates de mensagem obrigatórios

3. PREVENTIVO (fazer ANTES de acontecer):
   - Manter número de backup sempre conectado
   - Não exceder 500 mensagens/dia via Baileys
   - Nunca enviar mensagens para contatos que não iniciaram conversa
```

---

### 📞 Guia do Televendas

O televendas usa o WhatsApp como canal ativo de vendas: busca clientes, oferece produtos e fecha pedidos.

#### Fluxo de Trabalho Diário

```
08:00  Abrir Chatwoot (chat.awamotos.com)
       └─ Verificar conversas pendentes da noite anterior
       └─ Responder mensagens em aberto (prioridade: B2B primeiro)

08:30  Revisar lista de leads do dia
       └─ Magento Admin > Smart Suggestions > Sugestões Pendentes
       └─ Filtrar por "WhatsApp Habilitado = Sim"

09:00  Iniciar abordagem ativa (respeitando LGPD)
       └─ APENAS clientes com whatsapp_optin = Sim
       └─ Usar templates aprovados (ver seção abaixo)

12:00  Pausa — bot continua respondendo automaticamente

13:00  Retomar atendimento
       └─ Verificar conversas que o bot não resolveu
       └─ Conversas com label "humano-necessario" são prioridade

17:30  Wrap-up
       └─ Resolver ou transferir todas as conversas abertas
       └─ Marcar conversas pendentes com label + nota interna
       └─ Atualizar status: "Resolvida" ou "Pendente"
```

#### Como Ler o Contexto do Cliente (Chatwoot)

Quando uma conversa chega, o sistema já identifica o cliente automaticamente. Consulte o painel lateral:

| Campo | O que significa | Como usar |
|---|---|---|
| **Nome / E-mail** | Dados do Magento (se cadastrado) | Personalizar: "Oi João!" |
| **Label `b2b-aprovado`** | Cliente empresa aprovado | Oferecer preços de atacado |
| **Label `b2b-pendente`** | Empresa aguardando aprovação | Informar: "Seu cadastro está em análise" |
| **Label `lead-novo`** | Não está no Magento | Oportunidade! Cadastrar e nutrir |
| **Nota interna (⚙️)** | Últimos 3 pedidos + valores | "Vi que comprou bagageiro mês passado..." |
| **Grupo de cliente** | Varejo / Atacado / VIP / Revendedor | Determina tabela de preços |

#### Templates de Abordagem (Aprovados)

> ⚠️ **REGRA DE OURO:** Só enviar mensagem proativa para clientes com `whatsapp_optin = Sim`.
> Respostas a mensagens do cliente (reativas) podem ser enviadas livremente dentro da janela de 24h.

**1. Sugestão de Recompra (cliente com histórico):**
```
Oi {nome}! 😊 Tudo bem?

Vi que faz um tempo desde sua última compra de {produto_anterior}.

Temos novidades que combinam com sua {moto}:
🔧 {produto_sugerido_1} — R$ {preco_1}
🔧 {produto_sugerido_2} — R$ {preco_2}

Quer que eu separe algum? 🏍️
```

**2. Acompanhamento pós-orçamento:**
```
Oi {nome}! 👋

Passando pra saber se conseguiu avaliar o orçamento que enviei.

Se tiver alguma dúvida sobre as peças ou precisar ajustar
quantidades, é só me falar! 😊

Posso te ajudar com mais alguma coisa?
```

**3. Produto de volta ao estoque:**
```
Oi {nome}! 🎉 Boa notícia!

O {produto} que você procurou voltou ao estoque!
Temos {qty} unidades disponíveis.

Preço especial pra você: R$ {preco}
Quer garantir o seu? 🏍️
```

**4. Cliente B2B — nova tabela de preços:**
```
Oi {nome}! 📋

Atualizamos nossa tabela de preços para revendedores.

Destaques:
• {produto_1}: de R$ {preco_antigo} por R$ {preco_novo}
• {produto_2}: condição especial acima de {qtd} unidades

Posso enviar a tabela completa em PDF? 📎
```

#### Como Transferir para o Bot / Voltar do Bot

**Assumir conversa do bot:**
- A conversa aparece na inbox com label `bot-handoff`
- Clicar em "Assign to me"
- Responder normalmente — o bot para de intervir naquela conversa

**Devolver conversa ao bot:**
- Remover assignee da conversa
- Adicionar label `bot-ativo`
- O Typebot retoma na próxima mensagem do cliente

#### Atalhos do Chatwoot (Canned Responses)

Configurar em: Chatwoot > Settings > Canned Responses

| Atalho | Texto |
|---|---|
| `/oi` | Olá! Bem-vindo à AWA Motos! Como posso te ajudar? 🏍️ |
| `/frete` | O frete é calculado no carrinho pelo CEP. Qual seu CEP? |
| `/pix` | Aceitamos PIX com 5% de desconto à vista. Posso gerar o link? |
| `/prazo` | Prazo de envio: 1-3 dias úteis após confirmação do pagamento. |
| `/b2b` | Para compras no atacado, preciso do CNPJ. Pode informar? |
| `/fitment` | Posso verificar compatibilidade. Qual marca, modelo e ano da moto? |
| `/rastreio` | Vou verificar o rastreamento. Qual o número do pedido ou e-mail? |
| `/horario` | Atendemos seg-sex 8h-18h e sábado 8h-12h. |
| `/troca` | Para trocas: awamotos.com/politica-troca ou informe o pedido. |
| `/obrigado` | Obrigado por comprar na AWA Motos! Boa pilotada! 🏍️😄 |

#### Métricas e Metas do Televendas

| Métrica | Meta Diária | Onde ver |
|---|---|---|
| Conversas atendidas | ≥ 20 | Chatwoot > Reports > Agent |
| Tempo médio de resposta | < 5 min | Chatwoot > Reports > Agent |
| Conversas resolvidas | ≥ 80% | Chatwoot > Reports > Agent |
| Pedidos via WhatsApp | ≥ 3 | Magento > Sales > Orders |
| CSAT (satisfação) | ≥ 4.0/5.0 | Chatwoot > Reports > CSAT |

---

### 💬 Guia do Atendente (Suporte / SAC)

O atendente resolve dúvidas, problemas e solicitações pós-venda vindas pelo WhatsApp.

#### Política de Atendimento

| Regra | Detalhe |
|---|---|
| **Tempo de primeira resposta** | Até 5 minutos durante horário comercial |
| **Horário** | Seg-Sex 08:00-18:00, Sáb 08:00-12:00 |
| **Fora do horário** | Bot responde automaticamente com mensagem de ausência |
| **Tom de voz** | Profissional, simpático, objetivo. Emojis com moderação |
| **Idioma** | Português BR. Evitar gírias regionais e jargões técnicos |
| **Sigilo** | Nunca compartilhar dados de um cliente com outro |

#### Fluxo de Atendimento Passo a Passo

```
1. RECEBER
   └─ Conversa chega no Chatwoot (label "bot-handoff" ou "humano-necessario")
   └─ Ler nota interna com contexto do cliente
   └─ Clicar em "Assign to me"

2. IDENTIFICAR
   └─ Ler mensagens anteriores (o que o cliente falou com o bot)
   └─ Classificar o tipo:
      • Dúvida sobre produto → responder ou encaminhar catálogo
      • Problema no pedido → consultar no Magento
      • Troca/devolução → seguir política de trocas
      • Reclamação → escalar se necessário

3. RESOLVER
   └─ Responder de forma clara e objetiva
   └─ Se precisar de tempo: "Vou verificar e já retorno! 🔍"
   └─ Nunca deixar o cliente sem resposta por mais de 10 minutos
   └─ Usar canned responses (/atalhos) para respostas padrão

4. REGISTRAR
   └─ Adicionar nota interna com resumo da resolução
   └─ Aplicar labels corretas: "resolvido", "troca", "reclamacao"
   └─ Se gerou pedido ou cupom: registrar no Magento

5. ENCERRAR
   └─ Confirmar: "Posso te ajudar com mais alguma coisa?"
   └─ Aguardar resposta
   └─ Marcar conversa como "Resolved"
```

#### Consultas Frequentes e Como Resolver

**"Onde está meu pedido?"**
1. Pedir número do pedido ou e-mail
2. Magento Admin > Sales > Orders > buscar
3. Copiar código de rastreamento
4. Responder:
   ```
   Seu pedido #{numero} foi enviado em {data} pela {transportadora}.
   Código de rastreio: {codigo}
   Acompanhe: {link_rastreamento}
   ```

**"Quero trocar um produto"**
1. Verificar prazo (até 7 dias após recebimento)
2. Verificar condição (sem uso, embalagem original)
3. Se elegível:
   ```
   A troca pode ser feita! Vou enviar por e-mail o procedimento
   e a etiqueta de postagem. Qual e-mail devo usar?
   ```
4. Se não elegível: explicar o motivo com empatia

**"Produto chegou errado/danificado"**
1. Pedir foto do produto recebido
2. Comparar com pedido no Magento
3. Abrir RMA no Magento
4. Responder:
   ```
   Sentimos muito pelo inconveniente! 😔
   Já abri uma solicitação de troca (RMA #{numero}).
   Instruções de postagem vão por e-mail em instantes.
   ```

**"Peça serve na minha moto?"**
1. Perguntar: marca, modelo e ano da moto
2. Usar `/fitment` ou consultar no Magento (produto > aba Fitment)
3. Responder com lista de produtos compatíveis

**"Sou lojista, quero comprar no atacado"**
1. Usar atalho `/b2b`
2. Pedir CNPJ
3. Verificar no Magento se já tem cadastro B2B
4. Se não: orientar cadastro ou coletar dados
5. Transferir para equipe B2B (Team: B2B)

#### Labels Padrão do Chatwoot

| Label | Quando usar | Quem aplica |
|---|---|---|
| `bot-handoff` | Cliente pediu humano | Automática |
| `humano-necessario` | Bot não resolveu | Automática |
| `b2b-aprovado` | Cliente B2B ativo | Automática |
| `b2b-pendente` | Empresa aguardando aprovação | Automática |
| `lead-novo` | Contato não encontrado no Magento | Automática |
| `resolvido` | Problema resolvido | Manual |
| `troca` | Solicitação de troca/devolução | Manual |
| `reclamacao` | Reclamação formal | Manual |
| `urgente` | Escalado para gerência | Manual |
| `aguardando-cliente` | Esperando resposta do cliente | Manual |

#### Escalação de Problemas

| Situação | Escalar para | Como |
|---|---|---|
| Erro no sistema (site fora, carrinho bugado) | TI (Jess) | Nota interna + grupo TI |
| Reclamação grave (produto danificou moto) | Gerência | Label `urgente` + nota |
| Cliente ameaça Procon/Reclame Aqui | Gerência | Transferir + alerta imediato |
| Dúvida técnica sobre fitment complexo | Televendas | Team: Vendas |
| Pedido B2B acima de R$ 5.000 | Gerência Comercial | Team: B2B |

---

### 🛒 Guia do Cliente (Experiência no WhatsApp)

> Esta seção descreve a jornada do cliente no WhatsApp da AWA Motos.
> Use como referência para treinar a equipe e para testes de qualidade.

#### Como Iniciar uma Conversa

1. Salvar o número da AWA Motos nos contatos
2. Abrir o WhatsApp e enviar qualquer mensagem (ex: "Oi")
3. O bot responde automaticamente com o menu principal:

```
Olá! 🏍️ Bem-vindo à AWA Motos!
Sou o assistente virtual. Como posso te ajudar?

1️⃣ 🔍 Buscar Peças
2️⃣ 🏍️ Buscar por Moto (Fitment)
3️⃣ 📦 Rastrear Pedido
4️⃣ 💬 Falar com Atendente
5️⃣ 🏢 Sou Lojista (B2B)
6️⃣ ❓ Outra Pergunta (IA)

Digite o número da opção desejada.
```

#### Jornada 1 — Buscar Peça por Moto (Fitment)

```
Cliente:  "2" (ou "Buscar por Moto")

Bot:      "Qual a marca da sua moto?"
          • Honda
          • Yamaha
          • Outra marca

Cliente:  "Honda"

Bot:      "Qual o modelo?"
          • CG 160 Titan
          • CG 160 Fan
          • Bros 160
          • XRE 300
          • CB 300
          (ver mais)

Cliente:  "CG 160 Titan"

Bot:      "Encontrei 40 peças compatíveis com Honda CG 160 Titan! 🏍️

          🔧 Bagageiro Traseiro Pro Tork — R$ 89,90
          🔧 Retrovisor GVS Esportivo — R$ 45,00
          🔧 Protetor de Motor Chapam — R$ 159,90

          Para ver no site, clique no link.
          Posso te ajudar com mais alguma coisa?"
```

#### Jornada 2 — Rastrear Pedido

```
Cliente:  "3" (ou "Rastrear Pedido")

Bot:      "Para rastrear, preciso do número do pedido
           ou o e-mail da compra. Qual prefere informar?"

Cliente:  "Pedido 000000120"

Bot:      "📦 Pedido #000000120
           Status: Enviado ✅
           Transportadora: Correios
           Código de rastreio: BR123456789SP
           Rastreie aqui: https://rastreamento.correios.com.br/...

           Posso ajudar com mais alguma coisa?"
```

#### Jornada 3 — Falar com Atendente Humano

```
Cliente:  "4" (ou "Falar com atendente")

Bot:      "Vou te transferir para um dos nossos atendentes! 💬

           ⏰ Horário: Seg-Sex 8h-18h, Sáb 8h-12h

           Enquanto aguarda, pode nos contar o motivo do contato?
           Assim o atendente já vai preparado!"

Cliente:  "Recebi a peça errada no meu pedido"

Bot:      "Entendi! Um atendente vai assumir em instantes.
           Obrigado pela paciência! 😊"

[Conversa transferida para Chatwoot → Equipe Suporte]
```

#### Jornada 4 — Cadastro B2B (Lojista)

```
Cliente:  "5" (ou "Sou Lojista")

Bot:      "Ótimo! 🏢 Para acessar preços de atacado,
           preciso de algumas informações.

           Qual o CNPJ da sua empresa?"

Cliente:  "12.345.678/0001-99"

Bot:      "Encontrei:
           🏢 Razão Social: Motopeças Silva Ltda
           📍 Araraquara - SP
           Está correto? [Sim] [Não]"

Cliente:  "Sim"

Bot:      "Qual o segmento da sua empresa?
           • Motopeças (revenda)
           • Oficina mecânica
           • Revendedor online
           • Outro"

Cliente:  "Motopeças"

Bot:      "Cadastro recebido! ✅
           Nossa equipe B2B vai analisar e entrar em contato
           em até 24 horas com condições especiais.

           Enquanto isso, visite: https://awamotos.com"
```

#### Jornada 5 — Pergunta Livre (IA)

```
Cliente:  "6" (ou "Outra Pergunta")

Bot:      "Nosso assistente virtual vai te ajudar! 🤖
           Digite sua pergunta:"

Cliente:  "Qual o melhor bagageiro para entregar comida com CG 160?"

Bot:      "Para entregas com a Honda CG 160, recomendo o
           Bagageiro Traseiro Reforçado Pro Tork, que suporta
           até 18kg — ideal para baús de delivery.

           Preço: R$ 89,90
           Veja no site: https://awamotos.com/...

           Quer saber mais ou ver outras opções?"
```

#### Notificações Automáticas (com opt-in)

Clientes que autorizaram recebem estas mensagens automáticas:

| Evento | Mensagem | Quando |
|---|---|---|
| Pedido confirmado | "✅ Pedido #{id} confirmado! Valor: R$ {total}" | Imediato |
| Pagamento aprovado | "💰 Pagamento confirmado! Preparando seu pedido" | Imediato |
| Pedido enviado | "🚚 Pedido enviado! Rastreio: {codigo}" | Imediato |
| Follow-up | "Sua peça chegou bem? 😊" | 7 dias após entrega |
| Sugestão recompra | "Hora da revisão! Peças para sua {moto} 🔧" | 30 dias |
| Carrinho abandonado | "Vi que deixou itens no carrinho 🛒" | 1h, 24h, 72h |

#### FAQ do Cliente

**P: Posso comprar direto pelo WhatsApp?**
R: Sim! O bot monta o carrinho e gera um link de pagamento seguro. Você finaliza no navegador com PIX, cartão ou boleto.

**P: Meus dados estão seguros?**
R: Sim! Seguimos a LGPD. Só enviamos mensagens com sua autorização. Para parar, responda "SAIR" a qualquer momento.

**P: O bot não entendeu minha pergunta, o que faço?**
R: Digite "4" ou "atendente" para falar com um humano. Seg-sex 8h-18h.

**P: Como cancelo as notificações por WhatsApp?**
R: Responda "SAIR" ou "PARAR" a qualquer mensagem. Também pode desativar em Minha Conta > Preferências no site.

**P: Quais formas de pagamento?**
R: PIX (5% desconto), cartão de crédito (até 6x sem juros), boleto, e "A Combinar" para B2B.

**P: Qual o prazo de entrega?**
R: 1-3 dias úteis após confirmação do pagamento. Frete calculado no checkout.

---

### 📊 Boas Práticas — Todos os Perfis

#### O que FAZER

| Prática | Motivo |
|---|---|
| ✅ Responder em até 5 minutos | WhatsApp tem expectativa de resposta rápida |
| ✅ Personalizar com nome do cliente | Aumenta confiança e conversão |
| ✅ Usar emojis com moderação (1-3 por msg) | Amigável sem parecer spam |
| ✅ Enviar fotos dos produtos quando relevante | Visual vende peças/acessórios |
| ✅ Confirmar antes de fechar pedido | Evita erros e devoluções |
| ✅ Registrar nota interna no Chatwoot | Próximo atendente terá contexto |
| ✅ Verificar compatibilidade (fitment) | Confiança é diferencial da AWA |
| ✅ Respeitar o "SAIR" do cliente imediatamente | LGPD obriga — respeito fideliza |

#### O que NÃO FAZER

| Prática | Motivo |
|---|---|
| ❌ Enviar msg para quem não autorizou (opt-in) | LGPD — multa e ban |
| ❌ Enviar 3+ mensagens seguidas sem resposta | Spam — denúncia e ban |
| ❌ Copiar/colar preços sem verificar no sistema | Preços mudam com frequência |
| ❌ Prometer prazo sem consultar estoque | Frustração se não cumprir |
| ❌ Compartilhar dados de um cliente com outro | Violação de privacidade |
| ❌ Enviar áudios longos (> 30 segundos) | Clientes preferem texto scanável |
| ❌ Usar linguagem informal demais | Manter tom profissional-amigável |
| ❌ Deixar conversa sem resolução > 24h | Cliente procura concorrente |
| ❌ Discutir com o cliente | Escalar para gerência |

#### Glossário

| Termo | Significado |
|---|---|
| **Fitment** | Compatibilidade peça × moto (qual peça serve em qual modelo) |
| **Opt-in** | Autorização do cliente para receber mensagens (LGPD) |
| **Opt-out** | Cliente pediu para não receber mais mensagens |
| **Handoff** | Transferência do bot para atendente humano |
| **B2B** | Business-to-Business — venda para empresas/lojistas |
| **RFM** | Recência, Frequência, Monetário — perfil de compra |
| **Canned Response** | Resposta pré-definida (/atalho) no Chatwoot |
| **Label** | Etiqueta/tag na conversa do Chatwoot |
| **LGPD** | Lei Geral de Proteção de Dados (Lei 13.709/2018) |
| **RMA** | Return Merchandise Authorization — autorização de devolução |
| **CSAT** | Customer Satisfaction Score — nota de satisfação |

---

## Fase 0 — Infraestrutura Base ✅

> **Status:** Concluída
> **Resultado:** Evolution API + N8N + Typebot + IA (Groq Llama 3.3 70B) operacionais

### 0.1 Evolution API ✅
- [x] Docker compose + domínio `wpp.awamotos.com` + SSL
- [x] WhatsApp conectado + webhook Chatwoot configurado
- [x] SmartSuggestions apontando para Evolution

### 0.2 N8N ✅
- [x] Docker compose + domínio `n8n.awamotos.com` + SSL
- [x] Credenciais Magento + Evolution API configuradas
- [x] 4 workflows ativos e funcionando

### 0.3 Typebot ✅
- [x] Docker compose + viewer `bot.awamotos.com`
- [x] Integração Evolution API nativa
- [x] Fluxo com 12 grupos, 48 edges, 11 variáveis

### 0.4 IA ✅
- [x] Groq API (Llama 3.3 70B) configurada no N8N
- [x] Code node com `this.helpers.httpRequest()`
- [x] 4 cenários testados: saudação, fitment, Yamaha, B2B

### 0.5 SmartSuggestions WhatsApp
- [ ] Habilitar integração no Admin
- [ ] Configurar provider evolution + template RFM
- [ ] Testar envio manual de sugestão

---

## Fase 1 — Atendimento Unificado (Semana 2-3)

### 1.1 Evolution ↔ Chatwoot ✅
- [x] Inbox WhatsApp criada no Chatwoot

### 1.2 Identificação de Cliente ✅
- [x] Workflow N8N de identificação automática

### 1.3 Módulo WhatsAppCommerce ✅
- [x] 21 arquivos, 10 REST APIs implementadas

**Pendente:**
- [ ] Configurar horário de atendimento no Chatwoot
- [ ] Testar handoff completo bot → humano → bot
- [ ] Implementar atributo EAV `whatsapp_optin`

---

## Fase 2 — Catálogo e Vendas (Semana 5-8)

### 2.1 Typebot — Fluxo de Catálogo
- [x] Menu principal com 6 opções
- [x] Sub-fluxo Fitment (Honda, Yamaha, Outra)
- [x] Sub-fluxo IA (pergunta livre)
- [ ] Sub-fluxo categorias
- [ ] Botões interativos do WhatsApp

### 2.2 APIs REST ✅
- [x] 10 endpoints implementados (catalog, cart, tracking)

### 2.3 Carrinho e Pagamento
- Fase 2: link de checkout (link gerado pela API)
- Fase 3: PIX inline
- Fase 4: checkout completo no WhatsApp

### 2.4 IA — Assistente de Vendas ✅
- [x] Workflow N8N com Groq Llama 3.3 70B funcionando
- [ ] Enriquecer com dados reais (chamar API fitment/catálogo)

---

## Fase 3 — Automações de Vendas (Semana 9-12)

> ⚠️ Migrar para Cloud API Oficial antes desta fase

### 3.1 Carrinho Abandonado via WhatsApp
- [ ] Estender AbandonedCart com canal WhatsApp
- [ ] 3 ondas: 1h, 24h, 72h

### 3.2 Notificações de Pedido ✅
- [x] Observers para: pedido confirmado, pago, enviado, reembolso
- [x] Config admin para habilitar/desabilitar cada notificação
- [x] POST para N8N webhook `nova-ordem` no evento `sales_order_place_after`
- [x] Configs ativadas: placed=1, paid=1, shipped=1, refunded=0

### 3.3 Follow-up Pós-Compra ✅
- [x] Workflow N8N implementado (7 dias + 30 dias)

### 3.4 Promoções e Disparos em Massa
- [ ] Templates de campanha + segmentação + métricas

---

## Fase 4 — B2B no WhatsApp (Semana 13-16) ✅

- [x] Cadastro B2B via API REST (CNPJ + validação ReceitaWS + BrasilAPI)
- [x] Cotação B2B (submit, list, detail, accept via API)
- [x] Recompra rápida (list orders, reorder by ID, reorder last)
- [x] Alertas B2B cron (estoque, novos produtos, crédito)

---

## Fase 5 — Operacional & ERP (Semana 17-20)

- [ ] Sync pedidos Magento → ERP via N8N
- [ ] Alertas de estoque baixo
- [ ] Health check + monitoramento
- [ ] Dashboard WhatsApp para admin

---

## Fase 6 — SEO & Marketing (Semana 21-24)

- [ ] Meta descriptions com IA
- [ ] Reviews via WhatsApp
- [ ] Posts automáticos em redes sociais
- [ ] Retargeting inteligente

---

## Conformidade e Segurança

### LGPD ✅
- [x] Atributo EAV `whatsapp_optin` (attribute_id=208, default 0)
- [x] Tabela `awa_whatsapp_consent_log` (customer_id, phone, optin, source, IP, user_agent)
- [x] Checkbox opt-in no checkout (Rokanthemes OPC + standard checkout)
- [x] Opt-in no bot (Typebot grupo "Opt-in Notificacoes" com webhook N8N)
- [x] Descadastro: "NAO" no bot → opt-out automático via webhook
- [x] Todos os disparos verificam opt-in (`hasWhatsappConsent()` no Observer)
- [x] REST API: GET/POST `/V1/awa-whatsapp/optin` para N8N/Typebot
- [x] Controller AJAX: `POST /whatsappcommerce/checkout/saveoptin` para checkout

### WhatsApp Business Policy
- [ ] Baileys apenas para atendimento receptivo (Fases 0-2)
- [ ] Cloud API antes dos disparos em massa (Fase 3)
- [ ] Templates pré-aprovados pelo Meta
- [ ] Respeitar janela de 24h
- [ ] Aquecimento gradual (1k → 10k → 100k/dia)

### Segurança Técnica
- [ ] Tokens em variáveis de ambiente
- [ ] HMAC validation em webhooks
- [ ] Rate limiting: 60 req/min por token
- [ ] Logs sem PII sensível
- [ ] Backup diário dos volumes Docker
- [ ] Health check a cada 5 min

---

## Métricas de Sucesso

| KPI | Meta Fase 2 | Meta Fase 6 |
|---|---|---|
| Conversão WhatsApp | 10% | 20% |
| Pedidos via WhatsApp/mês | 50 | 300 |
| Carrinhos recuperados | 15% | 30% |
| Tempo médio de resposta | < 5 min | < 1 min (IA) |
| Clientes B2B no WhatsApp | 20 | 100 |
| NPS | 60 | 80 |
| Reviews/mês | 10 | 50 |

---

## Custos Estimados

| Item | Custo Mensal | Observação |
|---|---|---|
| Evolution API | R$ 0 | Self-hosted |
| N8N | R$ 0 | Self-hosted |
| Typebot | R$ 0 | Self-hosted |
| Chatwoot | R$ 0 | Self-hosted |
| Groq API (IA) | R$ 0 | Free tier |
| **Meta Cloud API** | ~R$ 0,25/conversa | A partir da Fase 3 |
| **OpenAI** (futuro) | ~R$ 50-150/mês | Volume maior |
| **Upgrade VPS** | ~R$ 80-150/mês | Se RAM > 90% |
| **Total Fases 0-2** | **R$ 0/mês** | Tudo gratuito |
| **Total Fase 3+** | **R$ 100-300/mês** | Cloud API + IA |

> **Economia vs Suri Shop:** R$ 400-1.700/mês = R$ 4.800-20.400/ano

---

## Status de Implementação (atualizado 2026-04-13)

### ✅ Fase 0 — Infraestrutura Base (COMPLETA)
- [x] **0.1** Evolution API v2.3.7 em `wpp.awamotos.com` (Baileys)
- [x] **0.2** N8N 1.88.0 em `n8n.awamotos.com` (credenciais Magento, Evolution, Groq)
- [x] **0.3** Typebot 3.16.1 em `bot.awamotos.com` (viewer) + builder local porta 3001
- [x] **0.4** IA Groq Llama 3.3 70B configurada (workflow `HO9AIHfVJ3E2CjWN`)
- [x] **0.5** SmartSuggestions WhatsApp habilitado (provider=evolution, auto_send=off por segurança Baileys)

### ✅ Fase 1 — Atendimento Unificado (PARCIAL)
- [x] **1.1** Inbox WhatsApp no Chatwoot conectada via Evolution API
- [x] **1.2** Typebot bot de triagem com 6 opções de menu
- [x] **1.3** Módulo WhatsAppCommerce (21 arquivos): APIs catálogo, busca, fitment, tracking
- [ ] **1.4** WhatsApp número de produção conectado (QR Code pendente)
- [x] **2.0** Roteamento automático de atendentes ERP (9 atendentes mapeados)
- [x] **2.1** LGPD checkout opt-in (checkbox → AJAX → customer attribute)
- [x] **2.2** REST API opt-in/opt-out (Typebot/N8N → Magento)
- [x] **2.3** Order notifications (4 events → N8N webhook + WhatsApp)

### ✅ IA com Dados Reais do Catálogo (COMPLETA — 2026-04-14)
- [x] Workflow N8N com 3 estratégias: produto+marca+modelo (search), marca+modelo (fitment), produto (search)
- [x] Integração Magento REST APIs (`/catalog/search`, `/catalog/fitment`)
- [x] Typebot AI flow: pergunta → webhook N8N → resposta com produtos reais + preços + links
- [x] Loop de conversação: outra pergunta / voltar ao menu / falar com atendente
- [x] 6 cenários de teste passando (bagageiro CG 160, peças Fazer 250, retrovisor, saudação, B2B, retrovisor Fazer 250)

### ✅ Fase 2 — LGPD + Notificações (COMPLETA — 2026-04-13)
- [x] REST API opt-in/opt-out: GET/POST `/V1/awa-whatsapp/optin/:phone`
- [x] Checkout WhatsApp opt-in checkbox (OPC sortOrder 265 + standard checkout)
- [x] AJAX controller `SaveOptin` com form_key validation
- [x] Typebot: 3 novos grupos (Opt-in Notificacoes, Confirmacao com webhook, Recusado)
- [x] N8N workflow `NcWf3h559OZz49X2` — webhook `whatsapp-optin` → Magento API
- [x] Roteamento automático de atendentes (9 atendentes, 2382 clientes synced)
- [x] Order notifications ativadas (4 observers, N8N webhook nova-ordem)
- [x] Consent log LGPD com audit trail completo

### ✅ Fase 4 — B2B WhatsApp (COMPLETA — 2026-04-13)
- [x] B2B Registration API: validate CNPJ (ReceitaWS), register lead, check status
- [x] B2B Quote API: submit, list, detail, accept (integra QuoteRequestRepository)
- [x] B2B Reorder API: list orders, reorder by ID, reorder last
- [x] B2B Alerts Cron: estoque, novos produtos, lembrete de crédito (diário 9h)
- [x] Alertas B2B habilitados no admin (config `whatsapp_commerce/b2b/alerts_enabled`)
- [x] 12 endpoints REST B2B registrados no webapi.xml

### ✅ Fases 5-6 — APIs Operacionais e Marketing (COMPLETA — 2026-04-13)
- [x] Admin Dashboard API: vendas hoje, estoque, novos clientes, detalhe pedido, top selling
- [x] Review API: avaliação de produto via WhatsApp (salva como Review pendente)
- [x] Campaign API: broadcast por segmento (all_optin, recent_90d, b2b) + stats
- [x] Todas as 11 interfaces com preferências DI + logger dedicado
- [x] 30+ endpoints REST no webapi.xml (todos autenticados por Integration Token)

### 🔲 Pendente
- [ ] Escanear QR Code WhatsApp (Evolution API state=`connecting`)
- [ ] Teste end-to-end via WhatsApp real
- [ ] Migrar para Cloud API Oficial (antes dos disparos em massa)
- [ ] Templates WhatsApp pré-aprovados pelo Meta
- [ ] Backup diário dos volumes Docker
- [ ] Workflows N8N para B2B (cadastro, cotação, recompra)

---

