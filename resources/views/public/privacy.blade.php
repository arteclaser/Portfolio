@php $portfolio = \App\Models\Portfolio::current(); @endphp
@extends('layouts.public', ['title' => 'Privacidade', 'canonical' => route('privacy')])

@section('content')
<div class="container section">
    <div class="prose">
        <h1 class="section__title">Privacidade</h1>
        <p class="lead">Como este site trata informações de quem o visita. Este aviso descreve o funcionamento técnico do portfólio e não substitui a política de privacidade oficial do órgão responsável.</p>

        <h2>O que o site não faz</h2>
        <ul>
            <li>As páginas públicas não gravam cookies nem usam ferramentas de rastreamento ou estatística de terceiros.</li>
            <li>As fontes e imagens são servidas pelo próprio site, sem chamadas a serviços externos durante a navegação.</li>
            <li>As fotografias publicadas passam por processamento que remove metadados de localização (EXIF/GPS).</li>
        </ul>

        <h2>Conteúdo de terceiros</h2>
        <p>Vídeos do YouTube e publicações do Instagram só são carregados quando você clica para reproduzi-los ou exibi-los. A partir desse momento, o serviço externo (Google ou Meta) recebe dados da sua visita, conforme as políticas próprias desses serviços. Os vídeos usam o modo de privacidade avançada do YouTube (domínio youtube-nocookie.com). Se preferir, use o link para abrir o conteúdo diretamente no site de origem.</p>

        <h2>Preferências locais</h2>
        <p>Se você escolher o tema claro ou escuro, essa preferência fica guardada apenas no seu navegador (armazenamento local) e não é enviada ao servidor.</p>

        <h2>Dados de pessoas exibidas</h2>
        <p>Nomes, cargos, fotos e contatos de integrantes da equipe só aparecem quando cadastrados como públicos. Contatos marcados como internos nunca são exibidos.</p>

        <h2>Contato</h2>
        <p>
            @if ($portfolio->contact_email)
                Dúvidas ou solicitações sobre dados pessoais: <a href="mailto:{{ $portfolio->contact_email }}">{{ $portfolio->contact_email }}</a>.
            @else
                Dúvidas ou solicitações sobre dados pessoais devem ser encaminhadas ao órgão responsável pelo portfólio.
            @endif
        </p>
    </div>
</div>
@endsection
