@php $portfolio = \App\Models\Portfolio::current(); @endphp
@extends('layouts.public', ['title' => 'Acessibilidade', 'canonical' => route('accessibility')])

@section('content')
<div class="container section">
    <div class="prose">
        <h1 class="section__title">Acessibilidade</h1>
        <p class="lead">Este portfólio é desenvolvido para que todas as pessoas possam consultar as ações, inclusive quem usa leitor de tela, teclado, ampliação ou celular.</p>

        <h2>Meta e referências</h2>
        <p>A meta é atender às <a href="https://www.w3.org/TR/WCAG22/" rel="noopener noreferrer">Diretrizes de Acessibilidade para Conteúdo Web (WCAG) 2.2</a>, nível AA, do W3C. A acessibilidade de sítios mantidos por órgãos de governo é obrigatória pelo art. 63 da Lei Brasileira de Inclusão (Lei nº 13.146/2015), e o art. 8º, § 3º, VIII, da Lei de Acesso à Informação (Lei nº 12.527/2011) determina medidas para garantir a acessibilidade de conteúdo para pessoas com deficiência.</p>

        <h2>Recursos disponíveis</h2>
        <ul>
            <li>Link “Pular para o conteúdo” no início de cada página.</li>
            <li>Navegação completa por teclado, com foco sempre visível. Na galeria, use as setas para trocar de imagem e Esc para fechar.</li>
            <li>Tema claro e escuro (botão com ícone de lua no topo). Por padrão, o site segue a preferência do seu dispositivo.</li>
            <li>Redução de movimento respeitada quando ativada no sistema.</li>
            <li>Texto com tamanho mínimo de 16 px e layout que se reorganiza no celular e com ampliação, sem rolagem horizontal.</li>
            <li>Imagens com descrição textual preenchida pela equipe; imagens decorativas são ignoradas por leitores de tela.</li>
            <li>Vídeos e publicações externas só carregam quando você escolhe, e o link para o conteúdo original fica sempre disponível.</li>
        </ul>

        <h2>Como verificamos</h2>
        <p>As páginas passam por testes automatizados de acessibilidade (axe-core, regras WCAG A e AA), de navegação por teclado e de exibição em tela de celular. Testes automatizados não substituem a avaliação com pessoas usuárias: alguns problemas só são percebidos no uso real.</p>

        <h2>Encontrou alguma barreira?</h2>
        <p>
            @if ($portfolio->contact_email)
                Escreva para <a href="mailto:{{ $portfolio->contact_email }}">{{ $portfolio->contact_email }}</a> informando a página e o problema encontrado.
            @else
                Entre em contato com a equipe responsável pelo portfólio informando a página e o problema encontrado.
            @endif
        </p>
    </div>
</div>
@endsection
