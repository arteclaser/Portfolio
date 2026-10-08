<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Portfolio;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Support\Str;

/**
 * Conteúdo de demonstração. Só é criado em ambiente marcado como demonstração:
 * imagens abstratas geradas aqui mesmo (não são fotos reais), pessoas e
 * instituições fictícias e textos que se identificam como ilustrativos.
 */
class DemoContent
{
    public function __construct(
        private ActionWorkflow $workflow,
        private MediaStore $media,
        private PagePublisher $pages,
    ) {}

    /** @return array<string, string> e-mail => senha gerada para os usuários de demonstração */
    public function seed(Portfolio $portfolio, User $master): array
    {
        return \App\Support\Tenant::run($portfolio, fn () => $this->seedInTenant($portfolio, $master));
    }

    private function seedInTenant(Portfolio $portfolio, User $master): array
    {
        $portfolio->is_demo = true;
        $portfolio->save();

        $areas = Page::where('portfolio_id', $portfolio->id)->whereNull('parent_id')->orderBy('position')->get()->keyBy('title');
        $eco = $areas['Desenvolvimento Econômico'] ?? $areas->first();
        $ino = $areas['Inovação'] ?? $areas->skip(1)->first() ?? $eco;
        $tur = $areas['Turismo'] ?? $areas->skip(2)->first() ?? $eco;

        $program = Page::firstOrCreate(
            ['portfolio_id' => $portfolio->id, 'slug' => 'programa-empreender-exemplo'],
            ['title' => 'Programa Empreender (exemplo)', 'kind' => 'programa', 'parent_id' => $eco->id, 'position' => 0,
                'description' => 'Programa fictício usado para demonstrar páginas subordinadas.']
        );

        $members = [];
        foreach ([
            ['Integrante Demonstração 1', 'Coordenação', $eco], ['Integrante Demonstração 2', 'Inovação', $ino],
            ['Integrante Demonstração 3', 'Turismo', $tur], ['Integrante Demonstração 4', 'Apoio técnico', $eco],
        ] as $i => [$name, $role, $page]) {
            $members[] = TeamMember::firstOrCreate(['portfolio_id' => $portfolio->id, 'name' => $name], [
                'role_title' => $role, 'function' => 'Função ilustrativa', 'page_id' => $page->id, 'is_public' => true, 'position' => $i,
                'bio' => 'Perfil fictício para demonstração do portfólio.', 'contact_email' => 'interno'.($i + 1).'@exemplo.invalid', 'contact_is_public' => false,
            ]);
        }
        $partner = Partner::firstOrCreate(['portfolio_id' => $portfolio->id, 'name' => 'Instituição Parceira (exemplo)'], ['is_public' => true, 'description' => 'Parceiro fictício.']);

        $palette = [['#10243A', '#2155E8'], ['#0B3B2E', '#1F8A5B'], ['#3A2410', '#C2702B'], ['#1A1F3A', '#6B5BD2'], ['#10303A', '#1E9AB0'], ['#2A1033', '#B03A8C']];
        $items = [
            ['Conexões que impulsionam novas ideias', $ino, [$tur], 'Projetos, encontros e iniciativas para o desenvolvimento local.', 'Oficina', 'concluida', true],
            ['Conhecimento para quem empreende', $eco, [], 'Capacitação ilustrativa para empreendedores.', 'Capacitação', 'concluida', true],
            ['Novos olhares para o turismo local', $tur, [], 'Ação ilustrativa sobre experiências turísticas do território.', 'Programa', 'em_andamento', true],
            ['Formação e oportunidades', $program, [], 'Exemplo de ação vinculada a um programa.', 'Capacitação', 'planejada', false],
            ['Ideias em movimento', $ino, [], 'Exemplo de ação de inovação.', 'Evento', 'concluida', false],
            ['Experiências do nosso território', $tur, [$eco], 'Exemplo de ação associada a duas áreas.', 'Visita técnica', 'concluida', false],
        ];

        foreach ($items as $i => [$title, $page, $related, $summary, $type, $status, $featured]) {
            if (Action::where('portfolio_id', $portfolio->id)->where('slug', Slug::make($title))->exists()) {
                continue;
            }
            $action = $this->workflow->create($portfolio, $master, ['title' => $title, 'primary_page_id' => $page->id, 'summary' => $summary]);
            $this->workflow->save($action, $master, [
                'title' => $title,
                'summary' => $summary,
                'description' => "**Conteúdo ilustrativo para demonstração.** Este texto não descreve um evento real.\n\nUse este exemplo para conhecer o layout: descrição em parágrafos, listas e links.\n\n- Item de exemplo\n- Outro item de exemplo",
                'primary_page_id' => $page->id,
                'related_page_ids' => array_map(fn ($p) => $p->id, $related),
                'starts_on' => now()->startOfYear()->addMonths($i)->toDateString(),
                'location' => 'Local ilustrativo',
                'activity_type' => $type,
                'activity_status' => $status,
                'objectives' => 'Objetivo ilustrativo.',
                'results' => $i === 0 ? 'Resultado ilustrativo, apenas para demonstração.' : null,
                'is_featured' => $featured,
                'team' => [['id' => $members[$i % count($members)]->id, 'role' => 'Responsável (exemplo)']],
                'partner_ids' => $i % 2 === 0 ? [$partner->id] : [],
                'indicators' => $i === 0 ? [['label' => 'Participações em oficinas', 'value' => 40, 'unit' => 'participações', 'period' => 'Período ilustrativo', 'source' => 'Valor fictício, apenas para demonstração', 'is_participation' => true]] : [],
            ]);
            $version = $action->fresh('workingVersion')->workingVersion;
            foreach ([0, 1] as $n) {
                $file = $this->illustration($palette[($i + $n) % count($palette)], $i * 7 + $n);
                $m = $this->media->storeImageFromPath($file, $portfolio, $master, 'ilustracao.png');
                @unlink($file);
                $m->update(['alt' => 'Imagem abstrata ilustrativa', 'credit' => 'Ilustração gerada automaticamente']);
                $version->media()->create(['media_id' => $m->id, 'position' => $n, 'is_cover' => $n === 0, 'alt' => 'Imagem abstrata ilustrativa', 'caption' => 'Imagem ilustrativa', 'credit' => 'Ilustração gerada automaticamente']);
            }
            $this->workflow->publish($action->fresh(), $master, 'Conteúdo de demonstração');
        }

        foreach ([$eco, $ino, $tur, $program] as $page) {
            if ($page->blocks()->count() <= 1) {
                $page->blocks()->delete();
                $page->blocks()->createMany([
                    ['type' => 'apresentacao', 'position' => 0, 'settings' => ['kicker' => 'Área de atuação', 'heading' => null, 'text' => 'Texto de apresentação ilustrativo. Edite em Painel → Páginas → Blocos.']],
                    ['type' => 'acoes_destaque', 'position' => 1, 'settings' => ['heading' => 'Em destaque', 'limit' => 3]],
                    ['type' => 'lista_acoes', 'position' => 2, 'settings' => ['heading' => 'Todas as ações', 'limit' => 9]],
                    ['type' => 'indicadores', 'position' => 3, 'settings' => ['heading' => 'Resultados documentados']],
                    ['type' => 'equipe', 'position' => 4, 'settings' => ['heading' => 'Equipe']],
                ]);
            }
            $this->pages->publish($page->fresh(), $master, 'Demonstração');
        }

        // Usuários de demonstração com senhas aleatórias (nunca senhas fixas no código).
        $credentials = [];
        foreach ([['editor.demo@exemplo.invalid', 'Editor de Demonstração', User::EDITOR, [$eco->id, $ino->id]], ['colaborador.demo@exemplo.invalid', 'Colaborador de Demonstração', User::COLLABORATOR, [$tur->id]]] as [$email, $name, $role, $pages]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $password = Str::password(16, symbols: false);
            $user = new User;
            $user->forceFill(['portfolio_id' => $portfolio->id, 'name' => $name, 'email' => $email, 'password' => $password, 'role' => $role, 'is_active' => true, 'password_set_at' => now()])->save();
            $user->pages()->sync($pages);
            $credentials[$email] = $password;
        }

        return $credentials;
    }

    /** Gera uma imagem abstrata (formas geométricas), sem pessoas nem lugares reais. */
    private function illustration(array $colors, int $seed): string
    {
        mt_srand($seed + 11);
        $w = 1600;
        $h = 1000;
        $img = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = sscanf($colors[0], '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($colors[1], '#%02x%02x%02x');
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($img, 0, $y, $w, $y, $c);
        }
        for ($i = 0; $i < 9; $i++) {
            $alpha = mt_rand(70, 110);
            $c = imagecolorallocatealpha($img, 255, 255, 255, $alpha);
            $size = mt_rand(160, 620);
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $size, $size, $c);
        }
        for ($i = 0; $i < 5; $i++) {
            $c = imagecolorallocatealpha($img, 255, 255, 255, 100);
            imagesetthickness($img, mt_rand(4, 14));
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $c);
        }
        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
