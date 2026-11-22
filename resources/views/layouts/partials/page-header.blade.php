@php
    $headerTitle = $title ?? $headerTitle ?? '';
    $headerDescription = $description ?? $subtitle ?? null;
    $headerBreadcrumbs = $breadcrumbs ?? [];
    $headerActions = $actions ?? [];
@endphp

<div class="page-title-head h-auto d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 py-3 mb-3">
    <div class="flex-grow-1 min-w-0">
        @if(!empty($headerBreadcrumbs) && is_array($headerBreadcrumbs))
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb m-0 p-0 fs-12 mb-1">
                    @foreach($headerBreadcrumbs as $crumbKey => $crumbVal)
                        @php
                            if (is_array($crumbVal)) {
                                $crumbLabel = $crumbVal['label'] ?? $crumbVal['title'] ?? (is_string($crumbKey) ? $crumbKey : '');
                                $crumbUrl = $crumbVal['url'] ?? $crumbVal['href'] ?? null;
                            } elseif (is_string($crumbKey)) {
                                $crumbLabel = $crumbKey;
                                $crumbUrl = $crumbVal;
                            } else {
                                $crumbLabel = $crumbVal;
                                $crumbUrl = null;
                            }
                        @endphp

                        @if(!empty($crumbUrl) && !$loop->last)
                            <li class="breadcrumb-item">
                                <a href="{{ $crumbUrl }}" class="text-muted text-decoration-none">{{ $crumbLabel }}</a>
                            </li>
                        @else
                            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">{{ $crumbLabel }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        <h4 class="fs-18 fw-bold text-dark m-0 lh-base">{{ $headerTitle }}</h4>

        @if(!empty($headerDescription))
            <p class="text-muted fs-13 mb-0 mt-1">
                {{ $headerDescription }}
            </p>
        @endif
    </div>

    @if(!empty($headerActions) || View::hasSection('page_actions') || !empty($actions_html))
        <div class="d-flex align-items-center flex-wrap gap-2 flex-shrink-0">
            @if(!empty($headerActions) && is_array($headerActions))
                @foreach($headerActions as $action)
                    @php
                        $actionLabel = $action['label'] ?? $action['text'] ?? $action['title'] ?? '';
                        $actionUrl = $action['url'] ?? $action['href'] ?? null;
                        $actionIcon = $action['icon'] ?? null;
                        $actionClass = $action['class'] ?? 'btn btn-primary';
                        $actionType = $action['type'] ?? 'button';
                        $actionId = $action['id'] ?? null;
                        $actionTarget = $action['target'] ?? null;
                        $actionModal = $action['modal'] ?? null;
                        $actionAttrs = $action['attributes'] ?? $action['attrs'] ?? [];
                    @endphp

                    @if(!empty($actionUrl))
                        <a href="{{ $actionUrl }}"
                           @if($actionId) id="{{ $actionId }}" @endif
                           class="{{ $actionClass }}"
                           @if($actionTarget) target="{{ $actionTarget }}" @endif
                           @if($actionModal) data-bs-toggle="modal" data-bs-target="{{ $actionModal }}" @endif
                           @if(is_array($actionAttrs))
                               @foreach($actionAttrs as $attrKey => $attrVal)
                                   {{ $attrKey }}="{{ $attrVal }}"
                               @endforeach
                           @elseif(is_string($actionAttrs))
                               {!! $actionAttrs !!}
                           @endif
                        >
                            @if($actionIcon)
                                <i class="{{ $actionIcon }} me-1 align-middle"></i>
                            @endif
                            <span>{{ $actionLabel }}</span>
                        </a>
                    @else
                        <button type="{{ $actionType }}"
                                @if($actionId) id="{{ $actionId }}" @endif
                                class="{{ $actionClass }}"
                                @if($actionModal) data-bs-toggle="modal" data-bs-target="{{ $actionModal }}" @endif
                                @if(is_array($actionAttrs))
                                    @foreach($actionAttrs as $attrKey => $attrVal)
                                        {{ $attrKey }}="{{ $attrVal }}"
                                    @endforeach
                                @elseif(is_string($actionAttrs))
                                    {!! $actionAttrs !!}
                                @endif
                        >
                            @if($actionIcon)
                                <i class="{{ $actionIcon }} me-1 align-middle"></i>
                            @endif
                            <span>{{ $actionLabel }}</span>
                        </button>
                    @endif
                @endforeach
            @endif

            @yield('page_actions')

            @if(!empty($actions_html))
                {!! $actions_html !!}
            @endif
        </div>
    @endif
</div>
