@if ($paginator->hasPages())
    <nav aria-label="Paginação">
        <ul class="pagination">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="disabled" aria-disabled="true">Anterior</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="disabled">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Página {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
                @else
                    <span class="disabled" aria-disabled="true">Próxima</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
