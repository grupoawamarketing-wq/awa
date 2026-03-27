<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Rokanthemes\Blog\Model\PostFactory;
use Rokanthemes\Blog\Model\ResourceModel\Post as PostResource;

/**
 * Cria posts inaugurais no blog Rokanthemes para que a seção /blog
 * não fique vazia e o slider do blog na homepage tenha conteúdo.
 *
 * 5 posts:
 * 1. Guia de compatibilidade de peças
 * 2. Vantagens do programa B2B
 * 3. Cuidados com retrovisor
 * 4. Dicas para viagem de moto
 * 5. Como escolher um bagageiro
 *
 * Seed idempotente via model/resource do Rokanthemes_Blog.
 *
 * @see docs/AUDITORIA_TEMA_AYO.md — seção 11
 */
class AyoBlogPostsSeed implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LoggerInterface $logger,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $connection = $this->moduleDataSetup->getConnection();
            $postTable = $this->moduleDataSetup->getTable('rokanthemes_blog_post');
            if (!$connection->isTableExists($postTable)) {
                $this->logger->warning('[AyoBlogPostsSeed] Tabela de blog não encontrada; seed ignorado.');
                return $this;
            }

            $createdCount = 0;
            $skippedCount = 0;

            foreach ($this->getPostDefinitions() as $postData) {
                if ($this->createIfMissing($postData)) {
                    $createdCount++;
                    continue;
                }

                $skippedCount++;
            }

            $this->logger->info(
                sprintf(
                    '[AyoBlogPostsSeed] Seed concluido. Criados=%d, JaExistentes=%d.',
                    $createdCount,
                    $skippedCount
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoBlogPostsSeed] Erro ao criar posts: %s', $e->getMessage())
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoSeedContent::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPostDefinitions(): array
    {
        return [
            [
                'title'            => 'Como Verificar a Compatibilidade de Peças para Sua Moto',
                'identifier'       => 'como-verificar-compatibilidade-pecas-moto',
                'short_content'    => 'Aprenda a usar a busca por aplicação para encontrar peças que servem perfeitamente na sua moto. Evite erros na hora da compra.',
                'content'          => $this->postCompatibilidade(),
                'status'           => 1,
                'store_id'         => '0',
                'meta_title'       => 'Como Verificar Compatibilidade de Peças para Motos | AWA Motos',
                'meta_description' => 'Guia completo para verificar se a peça serve na sua moto. Dicas de busca por aplicação, compatibilidade e como evitar erros.',
                'created_at'       => '2026-01-15 10:00:00',
            ],
            [
                'title'            => 'Programa B2B: Vantagens Exclusivas para Lojistas e Oficinas',
                'identifier'       => 'programa-b2b-vantagens-lojistas-oficinas',
                'short_content'    => 'Conheça as vantagens do nosso programa B2B: preços de atacado, condições especiais e atendimento dedicado para profissionais.',
                'content'          => $this->postB2B(),
                'status'           => 1,
                'store_id'         => '0',
                'meta_title'       => 'Programa B2B — Atacado de Peças para Motos | AWA Motos',
                'meta_description' => 'Descubra como se cadastrar no programa B2B da AWA Motos e aproveitar preços de atacado, condições especiais e atendimento dedicado.',
                'created_at'       => '2026-01-22 14:00:00',
            ],
            [
                'title'            => '5 Cuidados Essenciais com o Retrovisor da Sua Moto',
                'identifier'       => '5-cuidados-essenciais-retrovisor-moto',
                'short_content'    => 'O retrovisor é item obrigatório e essencial para a segurança. Confira 5 dicas para manter seus retrovisores em perfeito estado.',
                'content'          => $this->postRetrovisor(),
                'status'           => 1,
                'store_id'         => '0',
                'meta_title'       => '5 Cuidados com Retrovisor de Moto — Segurança e Manutenção | AWA Motos',
                'meta_description' => 'Dicas essenciais para cuidar do retrovisor da sua moto. Saiba quando trocar, como regular e quais modelos escolher.',
                'created_at'       => '2026-02-05 09:30:00',
            ],
            [
                'title'            => 'Guia Completo: Como Escolher o Bagageiro Ideal para Sua Moto',
                'identifier'       => 'guia-como-escolher-bagageiro-ideal-moto',
                'short_content'    => 'Bagageiros são essenciais para quem usa a moto no dia a dia. Saiba como escolher o modelo certo para sua moto.',
                'content'          => $this->postBagageiro(),
                'status'           => 1,
                'store_id'         => '0',
                'meta_title'       => 'Como Escolher Bagageiro para Moto — Guia Completo | AWA Motos',
                'meta_description' => 'Guia completo para escolher o bagageiro ideal para sua moto. Tipos, materiais, capacidade e dicas de instalação.',
                'created_at'       => '2026-02-12 11:00:00',
            ],
            [
                'title'            => 'Dicas para Viajar de Moto com Segurança e Conforto',
                'identifier'       => 'dicas-viajar-moto-seguranca-conforto',
                'short_content'    => 'Planejando uma viagem de moto? Confira nossas dicas sobre equipamentos, acessórios e preparação para pegar a estrada.',
                'content'          => $this->postViagem(),
                'status'           => 1,
                'store_id'         => '0',
                'meta_title'       => 'Dicas para Viajar de Moto — Segurança e Conforto | AWA Motos',
                'meta_description' => 'Dicas essenciais para viagem de moto: como preparar a moto, acessórios indispensáveis e cuidados na estrada.',
                'created_at'       => '2026-02-20 08:00:00',
            ],
        ];
    }

    /**
     * Cria o post somente quando o identificador ainda nao existe.
     *
     * @param array<string, mixed> $postData
     */
    private function createIfMissing(array $postData): bool
    {
        $post = $this->postFactory->create();
        $this->postResource->load($post, (string) $postData['identifier'], 'identifier');

        if ($post->getId()) {
            return false;
        }

        $publishTime = (string) ($postData['created_at'] ?? gmdate('Y-m-d H:i:s'));

        $post->setData([
            'title' => (string) $postData['title'],
            'identifier' => (string) $postData['identifier'],
            'short_content' => (string) $postData['short_content'],
            'content' => (string) $postData['content'],
            'meta_description' => (string) $postData['meta_description'],
            'meta_keywords' => '',
            'content_heading' => (string) $postData['title'],
            'is_active' => 1,
            'creation_time' => $publishTime,
            'publish_time' => $publishTime,
        ]);
        $post->setStores([0]);

        $this->postResource->save($post);

        return true;
    }

    // ========================================================================
    // CONTEÚDO DOS POSTS
    // ========================================================================

    private function postCompatibilidade(): string
    {
        return <<<'HTML'
<div class="blog-post-content">
    <h2>Por que a compatibilidade é tão importante?</h2>
    <p>Comprar uma peça incompatível com sua moto significa dor de cabeça, tempo perdido e custos de devolução. Na AWA Motos, facilitamos esse processo com a <strong>busca por aplicação</strong> — você informa a marca, modelo e ano da sua moto e vê apenas as peças compatíveis.</p>

    <h2>Como usar a busca por aplicação</h2>
    <ol>
        <li><strong>Acesse nosso site</strong> e localize o campo de busca por aplicação</li>
        <li><strong>Selecione a marca</strong> (Honda, Yamaha, Suzuki, etc.)</li>
        <li><strong>Escolha o modelo</strong> (CG 160, Fazer 250, Bros 160, etc.)</li>
        <li><strong>Informe o ano</strong> da sua moto</li>
        <li>Pronto! Serão exibidos apenas os produtos compatíveis</li>
    </ol>

    <h2>Dicas extras para acertar na compra</h2>
    <ul>
        <li>Sempre verifique a <strong>tabela de compatibilidade</strong> na página do produto</li>
        <li>Confira se há variações entre anos de fabricação do mesmo modelo</li>
        <li>Na dúvida, <strong>chame no WhatsApp</strong> — nossa equipe confirma a compatibilidade na hora</li>
        <li>Tire uma foto da peça atual — facilita a identificação</li>
    </ul>

    <h2>Modelos mais buscados</h2>
    <p>As motos com maior volume de peças em nosso catálogo:</p>
    <ul>
        <li>Honda CG 160 Titan / Fan / Start</li>
        <li>Honda Bros 160</li>
        <li>Honda XRE 300</li>
        <li>Honda CB 300</li>
        <li>Yamaha Fazer 250</li>
        <li>Yamaha Factor 150</li>
    </ul>

    <p>Tem dúvidas? <a href="https://wa.me/5516997367588">Fale conosco no WhatsApp</a> e ajudamos você a encontrar a peça certa!</p>
</div>
HTML;
    }

    private function postB2B(): string
    {
        return <<<'HTML'
<div class="blog-post-content">
    <h2>O que é o programa B2B da AWA Motos?</h2>
    <p>O programa B2B (Business to Business) da AWA Motos foi criado para atender <strong>lojistas, oficinas, revendedores e profissionais</strong> que compram peças e acessórios para motos em volume. Com ele, você tem acesso a condições exclusivas.</p>

    <h2>Vantagens do programa</h2>
    <ul>
        <li><strong>Preços de atacado</strong> — valores diferenciados para compras em volume</li>
        <li><strong>Condições de pagamento flexíveis</strong> — boleto faturado e prazos estendidos</li>
        <li><strong>Atendimento dedicado</strong> — equipe especializada em vendas B2B</li>
        <li><strong>Cotações personalizadas</strong> — solicite orçamentos para grandes quantidades</li>
        <li><strong>Catálogo completo</strong> — acesso a todo nosso portfólio de peças e acessórios</li>
        <li><strong>Entrega programada</strong> — agende entregas conforme sua necessidade</li>
    </ul>

    <h2>Como se cadastrar</h2>
    <ol>
        <li>Acesse a página de <a href="/b2b/register">Cadastro B2B</a></li>
        <li>Preencha os dados da empresa (CNPJ, Razão Social, Inscrição Estadual)</li>
        <li>Informe seus dados de contato</li>
        <li>Envie para análise — aprovação em até 24h úteis</li>
        <li>Após aprovação, acesse o portal com preços especiais</li>
    </ol>

    <h2>Quem pode se cadastrar?</h2>
    <p>O programa é destinado a empresas com CNPJ ativo nos seguintes segmentos:</p>
    <ul>
        <li>Lojas de peças e acessórios para motos</li>
        <li>Oficinas mecânicas de motos</li>
        <li>Revendedores e distribuidores</li>
        <li>Empresas de motoboy e entregas</li>
    </ul>

    <p><strong>Cadastre-se agora e comece a economizar!</strong> <a href="/b2b/register">Clique aqui para se cadastrar</a>.</p>
</div>
HTML;
    }

    private function postRetrovisor(): string
    {
        return <<<'HTML'
<div class="blog-post-content">
    <h2>A importância do retrovisor</h2>
    <p>O retrovisor é item <strong>obrigatório pelo Código de Trânsito Brasileiro</strong> (art. 105) e essencial para a segurança do motociclista. Ele permite visualizar o trânsito atrás sem precisar virar a cabeça, reduzindo o risco de acidentes.</p>

    <h2>5 cuidados essenciais</h2>

    <h3>1. Verifique a regulagem regularmente</h3>
    <p>Com o tempo, vibrações da moto podem desregular o retrovisor. Antes de cada viagem, confira se consegue ver o trânsito atrás sem pontos cegos grandes.</p>

    <h3>2. Troque retrovisores trincados imediatamente</h3>
    <p>Um espelho trincado distorce a imagem e pode causar acidentes. Além disso, é passível de multa. Troque assim que notar qualquer dano.</p>

    <h3>3. Limpeza correta</h3>
    <p>Use pano macio e limpa-vidros. Evite produtos abrasivos que podem arranhar o espelho. Limpe semanalmente para manter a visibilidade.</p>

    <h3>4. Aperte as fixações periodicamente</h3>
    <p>As vibrações da moto afrouxam parafusos com o tempo. Verifique as fixações a cada 500 km ou semanalmente em uso urbano.</p>

    <h3>5. Escolha o modelo correto</h3>
    <p>Retrovisores universais podem não se encaixar perfeitamente. Prefira modelos específicos para sua moto — na AWA Motos, você encontra retrovisores com compatibilidade garantida.</p>

    <p><a href="/catalogsearch/result/?q=retrovisor">Confira nossos retrovisores disponíveis</a> com filtro de compatibilidade por modelo.</p>
</div>
HTML;
    }

    private function postBagageiro(): string
    {
        return <<<'HTML'
<div class="blog-post-content">
    <h2>Para que serve um bagageiro de moto?</h2>
    <p>O bagageiro é um acessório que amplia a capacidade de carga da moto, ideal para quem usa a moto no dia a dia para trabalho, entregas ou viagens. Ele permite acoplar baús, bolsas e volumes de forma segura.</p>

    <h2>Tipos de bagageiro</h2>

    <h3>Bagageiro Traseiro (Suporte de Baú)</h3>
    <p>O mais comum. Instalado na parte traseira da moto, serve de base para baús de até 45L ou mais. Ideal para motoboys e viajantes.</p>

    <h3>Bagageiro Lateral</h3>
    <p>Instalado nas laterais, permite acoplar alforjes ou baús laterais. Ótimo para motos de viagem como a XRE 300 e Bros 160.</p>

    <h3>Bagageiro Dianteiro</h3>
    <p>Menos comum, mas útil para distribuir peso em viagens longas ou para motos de carga.</p>

    <h2>Como escolher</h2>
    <ol>
        <li><strong>Compatibilidade:</strong> Verifique se o modelo serve na sua moto (marca, modelo, ano)</li>
        <li><strong>Material:</strong> Aço carbono é mais resistente. Alumínio é mais leve. Ambos com tratamento anticorrosão</li>
        <li><strong>Capacidade:</strong> Considere o peso que pretende carregar</li>
        <li><strong>Acabamento:</strong> Pintura eletrostática dura mais. Cromado é mais bonito mas requer mais cuidado</li>
        <li><strong>Instalação:</strong> Prefira modelos com kit de fixação incluso e manual</li>
    </ol>

    <h2>Modelos mais populares na AWA Motos</h2>
    <ul>
        <li>Bagageiro CG 160 Titan — suporte para baú</li>
        <li>Bagageiro Bros 160 — reforçado para carga</li>
        <li>Bagageiro XRE 300 — adventure ready</li>
        <li>Bagageiro Fazer 250 — sport touring</li>
    </ul>

    <p><a href="/catalogsearch/result/?q=bagageiro">Encontre o bagageiro ideal para sua moto →</a></p>
</div>
HTML;
    }

    private function postViagem(): string
    {
        return <<<'HTML'
<div class="blog-post-content">
    <h2>Planejamento é tudo</h2>
    <p>Viajar de moto é uma experiência incrível, mas exige <strong>preparação adequada</strong> para garantir segurança e conforto. Confira nossas dicas para sua próxima aventura sobre duas rodas.</p>

    <h2>Antes de sair</h2>
    <ul>
        <li><strong>Revisão mecânica:</strong> Pneus, freios, corrente, óleo, iluminação</li>
        <li><strong>Documentação:</strong> CNH válida, CRLV em dia, seguro</li>
        <li><strong>Rota:</strong> Planeje paradas a cada 200-300 km para descanso</li>
        <li><strong>Clima:</strong> Consulte a previsão e prepare-se para variações</li>
    </ul>

    <h2>Acessórios indispensáveis</h2>
    <ul>
        <li><strong>Baú ou alforje:</strong> Para carga segura sem comprometer o equilíbrio</li>
        <li><strong>Bagageiro:</strong> Base para fixar o baú — verifique compatibilidade</li>
        <li><strong>Protetor de carenagem:</strong> Protege em caso de quedas</li>
        <li><strong>Protetores de mão:</strong> Essenciais contra frio e insetos a alta velocidade</li>
        <li><strong>Capa de chuva:</strong> Mesmo no verão, chuvas surpresa acontecem</li>
        <li><strong>Kit ferramentas básico:</strong> Chaves, fita isolante, parafusos reserva</li>
    </ul>

    <h2>Na estrada</h2>
    <ul>
        <li>Mantenha distância segura dos veículos à frente</li>
        <li>Use sempre o farol aceso (obrigatório)</li>
        <li>Pare para descansar a cada 2-3 horas</li>
        <li>Hidrate-se frequentemente</li>
        <li>Evite dirigir à noite em estradas desconhecidas</li>
    </ul>

    <h2>Prepare sua moto na AWA</h2>
    <p>Temos tudo que você precisa para equipar sua moto para viagem: <a href="/catalogsearch/result/?q=bagageiro">bagageiros</a>, <a href="/catalogsearch/result/?q=bau">baús</a>, <a href="/catalogsearch/result/?q=retrovisor">retrovisores</a> e mais. Use a busca por compatibilidade para encontrar os acessórios certos para sua moto!</p>
</div>
HTML;
    }
}
