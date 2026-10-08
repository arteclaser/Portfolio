@php
    $portfolio = $portfolio ?? \App\Models\Portfolio::current();
    $pageTitle = isset($title) && $title ? $title.' · '.$portfolio->name : $portfolio->name;
    $metaDescription = $metaDescription ?? ($portfolio->hero_description ?: $portfolio->subtitle);
    $isPreview = $preview ?? false;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)<meta name="description" content="{{ \Illuminate\Support\Str::limit($metaDescription, 300) }}">@endif
    @if ($isPreview || $portfolio->is_demo)<meta name="robots" content="noindex, nofollow">@endif
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    <meta property="og:site_name" content="{{ $portfolio->name }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="{{ $ogTitle ?? $pageTitle }}">
    @if ($metaDescription)<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($metaDescription, 300) }}">@endif
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    @isset($canonical)<meta property="og:url" content="{{ $canonical }}">@endisset
    @if (! empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
        @if (! empty($ogImageAlt))<meta property="og:image:alt" content="{{ $ogImageAlt }}">@endif
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="theme-color" content="#10243A">
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('assets/fonts/source-serif-4-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('portfolio.asset_version') }}">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ config('portfolio.asset_version') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v={{ config('portfolio.asset_version') }}" defer></script>
    @stack('head')
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
@if ($isPreview)
    <div class="notice-bar" role="note"><strong>Prévia</strong> · conteúdo ainda não aprovado, visível apenas para a equipe.</div>
@elseif ($portfolio->is_demo)
    <div class="notice-bar" role="note"><strong>Ambiente de demonstração</strong> · imagens e conteúdos ilustrativos, sem valor de registro.</div>
@endif
@include('partials.site-header', ['portfolio' => $portfolio, 'current' => $current ?? null])
<main id="conteudo" tabindex="-1">
    @yield('content')
</main>
@include('partials.site-footer', ['portfolio' => $portfolio])
@stack('body-end')
</body>
</html>
