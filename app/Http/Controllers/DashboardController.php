<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Services\AiService;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index()
    {
        $shopeeSetting = ShopeeSetting::current();
        if ($shopeeSetting->isConnected()) {
            try {
                app(ShopeeService::class)->syncAllLiveIfStale(25, app(StockMutationService::class));
            } catch (\Throwable) {
            }
        }

        $totalProducts = Product::count();
        $totalStock = (int) Product::sum('stock');
        $totalAssetValue = Product::selectRaw('SUM(stock * purchase_price) as total_val')->value('total_val') ?? 0;
        $lowStockCount = Product::whereColumn('stock', '<=', 'safety_stock')->count();
        $todayMutationsCount = StockMutation::whereDate('created_at', today())->count();

        // AI Daily Intelligence
        $latestAiAnalysis = null;
        if (auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('ai_advisor'))) {
            $latestAiAnalysis = AiAnalysis::latest()->first();
            if (! $latestAiAnalysis) {
                try {
                    $latestAiAnalysis = app(AiService::class)->generateSalesAnalysis(auth()->id());
                } catch (\Throwable) {
                    $latestAiAnalysis = null;
                }
            }
        }

        // Shopee metrics
        $shopeeConnectedCount = Product::whereNotNull('shopee_item_id')->count();
        $todayShopeeOrders = ShopeeOrder::whereDate('created_at', today())->count();
        $todayShopeeSales = ShopeeOrder::whereDate('created_at', today())->sum('total_amount');

        // Recent Mutations & Low Stock Items
        $recentMutations = StockMutation::with('product')->latest()->take(8)->get();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'safety_stock')->orderBy('stock')->take(6)->get();

        // Chart Data: Last 7 days mutations
        $chartLabels = [];
        $chartInbound = [];
        $chartOutbound = [];
        $chartShopee = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $chartLabels[] = $date->translatedFormat('d M');

            $chartInbound[] = (int) StockMutation::whereDate('created_at', $date)
                ->where('type', 'inbound')
                ->sum('qty');

            $chartOutbound[] = abs((int) StockMutation::whereDate('created_at', $date)
                ->where('type', 'outbound')
                ->sum('qty'));

            $chartShopee[] = abs((int) StockMutation::whereDate('created_at', $date)
                ->where('type', 'shopee_sale')
                ->sum('qty'));
        }

        $allProducts = Product::where('status', 'active')->orderBy('name')->get();
        $shopeeSetting = ShopeeSetting::current();

        return view('dashboard.index', compact(
            'totalProducts',
            'totalStock',
            'totalAssetValue',
            'lowStockCount',
            'todayMutationsCount',
            'shopeeConnectedCount',
            'todayShopeeOrders',
            'todayShopeeSales',
            'recentMutations',
            'lowStockProducts',
            'chartLabels',
            'chartInbound',
            'chartOutbound',
            'chartShopee',
            'allProducts',
            'shopeeSetting',
            'latestAiAnalysis'
        ));
    }

    /**
     * Real-time notifications API endpoint.
     */
    public function notifications(): JsonResponse
    {
        $shopeeSetting = ShopeeSetting::current();
        if ($shopeeSetting->isConnected()) {
            try {
                app(ShopeeService::class)->syncLiveOrdersIfStale(25, 7, app(StockMutationService::class));
            } catch (\Throwable) {
            }
        }

        // 1. Pending Orders awaiting packing
        $pendingOrders = ShopeeOrder::where('order_status', 'READY_TO_SHIP')
            ->whereDoesntHave('packingRecords', function ($q) {
                $q->where('status', 'completed');
            })
            ->latest()
            ->take(5)
            ->get();

        $pendingCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')
            ->whereDoesntHave('packingRecords', function ($q) {
                $q->where('status', 'completed');
            })
            ->count();

        // 2. Buyer Cancellations & Return requests (last 48 hours)
        $cancellations = ShopeeOrder::whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        $cancellationsCount = ShopeeOrder::whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])
            ->where('updated_at', '>=', now()->subHours(48))
            ->count();

        // 3. Low stock items
        $lowStockCount = Product::whereColumn('stock', '<=', 'safety_stock')->count();

        $totalBadge = $pendingCount + $cancellationsCount;

        return response()->json([
            'status' => 'success',
            'total_badge' => $totalBadge,
            'pending_count' => $pendingCount,
            'pending_orders' => $pendingOrders->map(fn ($o) => [
                'id' => $o->id,
                'order_sn' => $o->order_sn,
                'tracking_number' => $o->tracking_number,
                'buyer' => $o->buyer_username,
                'carrier' => $o->shipping_carrier ?? 'Shopee',
                'time_ago' => $o->created_at->diffForHumans(),
                'items_count' => count($o->items_data ?? []),
            ]),
            'cancellations_count' => $cancellationsCount,
            'cancellations' => $cancellations->map(fn ($o) => [
                'id' => $o->id,
                'order_sn' => $o->order_sn,
                'tracking_number' => $o->tracking_number,
                'status' => $o->order_status,
                'buyer' => $o->buyer_username,
                'time_ago' => $o->updated_at->diffForHumans(),
            ]),
            'low_stock_count' => $lowStockCount,
        ]);
    }
}
