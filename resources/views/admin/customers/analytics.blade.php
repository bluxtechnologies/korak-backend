@extends('layouts.admin')

@php
    $title = 'Analytics & Stats - @' . $customer->username;
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/apexcharts/apexcharts.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Analytics & Stats: @' . $customer->username,
    'subtitle' => 'Financial metrics, volume trends, category spending distribution, and order success analytics.',
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Analytics' => null,
    ],
    'actions' => [
        [
            'label' => 'Back to Profile',
            'icon' => 'ti ti-arrow-left',
            'class' => 'btn btn-outline-secondary',
            'url' => route('admin.customers.show', $customer),
        ],
    ],
])

{{-- Persistent Customer 360 Header --}}
@include('admin.customers.partials.header', ['activeTab' => 'analytics'])

{{-- KPI Summary Row --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">Lifetime Deposits</span>
                    <span class="avatar-sm rounded bg-success-subtle text-success d-inline-flex align-items-center justify-content-center">
                        <i class="ti ti-arrow-down-left fs-18"></i>
                    </span>
                </div>
                <h3 class="fs-20 fw-bold text-dark mb-1">₦{{ number_format($metrics['total_deposits'], 2) }}</h3>
                <span class="fs-11 text-muted">Total bank transfer & wallet inflows</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">Total Spend</span>
                    <span class="avatar-sm rounded bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="ti ti-shopping-cart fs-18"></i>
                    </span>
                </div>
                <h3 class="fs-20 fw-bold text-dark mb-1">₦{{ number_format($metrics['total_spent'], 2) }}</h3>
                <span class="fs-11 text-muted">Completed bill and VTU purchases</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">Order Success Rate</span>
                    <span class="avatar-sm rounded bg-info-subtle text-info d-inline-flex align-items-center justify-content-center">
                        <i class="ti ti-chart-pie fs-18"></i>
                    </span>
                </div>
                <h3 class="fs-20 fw-bold text-dark mb-1">{{ $metrics['success_rate'] }}%</h3>
                <span class="fs-11 text-muted">{{ $metrics['completed_orders'] }} of {{ $metrics['total_orders'] }} orders completed</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">Platform Fees Paid</span>
                    <span class="avatar-sm rounded bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center">
                        <i class="ti ti-receipt-tax fs-18"></i>
                    </span>
                </div>
                <h3 class="fs-20 fw-bold text-dark mb-1">₦{{ number_format($metrics['total_fees'], 2) }}</h3>
                <span class="fs-11 text-muted">Avg Order: ₦{{ number_format($metrics['average_order_value'], 2) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Visualizations Row --}}
<div class="row g-3 mb-3">
    {{-- 6-Month Trend Area Chart --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title fs-14 fw-bold mb-0">Monthly Cashflow Trend</h5>
                    <small class="text-muted fs-11">6-month comparison of wallet inflows vs spending (₦)</small>
                </div>
                <span class="badge bg-light text-dark font-monospace fs-11">Past 6 Months</span>
            </div>
            <div class="card-body p-3">
                <div id="chart-monthly-trend" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    {{-- Spend by Service Category Donut --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title fs-14 fw-bold mb-0">Spend by Category</h5>
                <small class="text-muted fs-11">Distribution across VTU and bill services</small>
            </div>
            <div class="card-body p-3 d-flex flex-column justify-content-center align-items-center">
                @if(!empty($chartData['category_series']))
                    <div id="chart-category-spend" class="w-100" style="min-height: 260px;"></div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="ti ti-chart-donut fs-32 text-secondary mb-2 d-block"></i>
                        <p class="fs-13 mb-0">No completed orders to categorize yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Bottom Row: Order Outcomes & Top Services --}}
<div class="row g-3">
    {{-- Order Outcome Breakdown --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title fs-14 fw-bold mb-0">Order Outcomes</h5>
                <small class="text-muted fs-11">Delivery success vs failed or refunded</small>
            </div>
            <div class="card-body p-3">
                @if($metrics['total_orders'] > 0)
                    <div id="chart-order-outcomes" style="min-height: 220px;"></div>
                    <div class="d-flex justify-content-around text-center pt-2 border-top fs-12">
                        <div>
                            <span class="text-success fw-bold d-block">{{ $metrics['completed_orders'] }}</span>
                            <span class="text-muted">Completed</span>
                        </div>
                        <div>
                            <span class="text-danger fw-bold d-block">{{ $metrics['failed_orders'] }}</span>
                            <span class="text-muted">Failed</span>
                        </div>
                        <div>
                            <span class="text-warning fw-bold d-block">{{ $metrics['refunded_orders'] }}</span>
                            <span class="text-muted">Refunded</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="ti ti-package-off fs-32 text-secondary mb-2 d-block"></i>
                        <p class="fs-13 mb-0">No orders placed by this customer yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Top Purchased Services --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title fs-14 fw-bold mb-0">Top Purchased Products & Services</h5>
                    <small class="text-muted fs-11">Most frequently purchased plans and services</small>
                </div>
                <a href="{{ route('admin.customers.orders', $customer) }}" class="btn btn-sm btn-link p-0 fs-12 text-decoration-none">
                    View All Orders <i class="ti ti-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                @if($topServices->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light">
                                <tr>
                                    <th>Service Name</th>
                                    <th>Category</th>
                                    <th>Times Ordered</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topServices as $service)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $service->service_name }}</td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary">
                                                {{ ucfirst(str_replace('_', ' ', (string) ($service->category ?? $service->type))) }}
                                            </span>
                                        </td>
                                        <td class="font-monospace fw-bold">{{ $service->order_count }}</td>
                                        <td class="fw-bold text-dark">₦{{ number_format($service->total_spent / 100, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="ti ti-shopping-cart-off fs-32 text-secondary mb-2 d-block"></i>
                        <p class="fs-13 mb-0">No service purchase history available yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modals & Scripts --}}
@include('admin.customers.partials.credit-debit-modal')
@include('admin.customers.partials.edit-profile-modal')
@endsection

@section('scripts')
<script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('admin/assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
@include('admin.customers.partials.scripts')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Monthly Trend Chart
        const monthlyLabels = @json($chartData['monthly_labels']);
        const monthlyInflows = @json($chartData['monthly_inflows']);
        const monthlyOutflows = @json($chartData['monthly_outflows']);

        const trendOptions = {
            series: [
                { name: 'Inflows (Deposits)', data: monthlyInflows },
                { name: 'Outflows (Spent)', data: monthlyOutflows }
            ],
            chart: {
                height: 280,
                type: 'area',
                toolbar: { show: false }
            },
            colors: ['#28c76f', '#7266ed'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: monthlyLabels,
                labels: { style: { fontSize: '11px' } }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return '₦' + Number(val).toLocaleString();
                    },
                    style: { fontSize: '11px' }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return '₦' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    }
                }
            },
            legend: { position: 'top' }
        };

        if (document.querySelector('#chart-monthly-trend')) {
            new ApexCharts(document.querySelector('#chart-monthly-trend'), trendOptions).render();
        }

        // 2. Spend by Category Donut
        const categoryLabels = @json($chartData['category_labels']);
        const categorySeries = @json($chartData['category_series']);

        if (categorySeries.length > 0 && document.querySelector('#chart-category-spend')) {
            const categoryOptions = {
                series: categorySeries,
                chart: {
                    type: 'donut',
                    height: 250
                },
                labels: categoryLabels,
                colors: ['#7266ed', '#28c76f', '#00cfe8', '#ff9f43', '#ea5455'],
                legend: { position: 'bottom', fontSize: '11px' },
                dataLabels: { enabled: false },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return '₦' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 });
                        }
                    }
                }
            };
            new ApexCharts(document.querySelector('#chart-category-spend'), categoryOptions).render();
        }

        // 3. Order Outcomes Donut
        const outcomeCounts = @json($chartData['order_outcomes']);
        const totalOutcomes = outcomeCounts.reduce((a, b) => a + b, 0);

        if (totalOutcomes > 0 && document.querySelector('#chart-order-outcomes')) {
            const outcomeOptions = {
                series: outcomeCounts,
                chart: {
                    type: 'donut',
                    height: 200
                },
                labels: ['Completed', 'Failed', 'Refunded'],
                colors: ['#28c76f', '#ea5455', '#ff9f43'],
                legend: { show: false },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector('#chart-order-outcomes'), outcomeOptions).render();
        }
    });
</script>
@endsection
