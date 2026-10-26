@props(['pages', 'libelle' => 'Pagination'])
@if ($pages->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-4" aria-label="{{ $libelle }}">
        @if ($pages->onFirstPage())<span></span>@else<a class="bouton bouton-secondaire" href="{{ $pages->previousPageUrl() }}" rel="prev">Page précédente</a>@endif
        <span class="text-sm text-ardoise">Page {{ $pages->currentPage() }} sur {{ $pages->lastPage() }}</span>
        @if ($pages->hasMorePages())<a class="bouton bouton-secondaire" href="{{ $pages->nextPageUrl() }}" rel="next">Page suivante</a>@else<span></span>@endif
    </nav>
@endif
