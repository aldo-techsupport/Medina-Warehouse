<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index()
    {
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();

        $totalProducts = Product::count();
        $totalStock = (int) Product::sum('stock');
        $totalAssetValue = Product::selectRaw('SUM(stock * purchase_price) as total_val')->value('total_val') ?? 0;
        $lowStockCount = Product::whereColumn('stock', '<=', 'safety_stock')->count();
        $todayMutationsCount = StockMutation::whereBetween('created_at', [$todayStart, $todayEnd])->count();

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

        // Shopee metrics: Exclude cancelled & returned orders to reflect real Shopee income
        $todayPrefix = date('ymd');
        $shopeeConnectedCount = Product::whereNotNull('shopee_item_id')->count();

        $validShopeeOrders = ShopeeOrder::whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED']);

        // Today's Shopee orders & sales
        $todayShopeeQuery = (clone $validShopeeOrders)->where(function ($q) use ($todayStart, $todayEnd, $todayPrefix) {
            $q->whereBetween('created_at', [$todayStart, $todayEnd])
                ->orWhere('order_sn', 'like', "{$todayPrefix}%");
        });
        $todayShopeeOrders = (clone $todayShopeeQuery)->count();
        $todayShopeeSales = (float) (clone $todayShopeeQuery)->sum('total_amount');

        // Total sales all-time and valid orders count
        $totalShopeeSales = (float) (clone $validShopeeOrders)->sum('total_amount');
        $totalShopeeOrders = (clone $validShopeeOrders)->count();

        // Penghasilan resmi Shopee:
        // 1. Dana Dilepas / Cair (COMPLETED)
        $releasedShopeeSales = (float) ShopeeOrder::where('order_status', 'COMPLETED')->sum('total_amount');
        // 2. Dana Akan Dilepas / Escrow (READY_TO_SHIP & PROCESSED)
        $escrowShopeeSales = (float) ShopeeOrder::whereIn('order_status', ['READY_TO_SHIP', 'PROCESSED'])->sum('total_amount');

        // Status breakdown: Khusus pesanan masuk ready to ship vs dalam perjalanan kurir
        $readyToShipOrders = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        $inTransitOrders = ShopeeOrder::where('order_status', 'PROCESSED')->count();
        $completedShopeeOrders = ShopeeOrder::where('order_status', 'COMPLETED')->count();
        $activePackingOrders = $readyToShipOrders;

        // Recent Mutations & Low Stock Items
        $recentMutations = StockMutation::with('product')->latest()->take(8)->get();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'safety_stock')->orderBy('stock')->take(6)->get();

        // Chart Data: Last 7 days mutations & Shopee sales (Single grouped queries)
        $sevenDaysAgo = today()->subDays(6)->startOfDay();
        $sevenDaysPrefix = $sevenDaysAgo->format('ymd');

        $mutationAggregates = StockMutation::whereBetween('created_at', [$sevenDaysAgo, $todayEnd])
            ->whereIn('type', ['inbound', 'outbound', 'shopee_sale'])
            ->selectRaw('DATE(created_at) as m_date, type, SUM(qty) as total_qty')
            ->groupBy('m_date', 'type')
            ->get()
            ->groupBy('m_date');

        $shopeeDailyCounts = ShopeeOrder::where('order_sn', '>=', $sevenDaysPrefix)
            ->whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN'])
            ->selectRaw('SUBSTRING(order_sn, 1, 6) as o_prefix, COUNT(*) as total_orders')
            ->groupBy('o_prefix')
            ->pluck('total_orders', 'o_prefix');

        $chartLabels = [];
        $chartInbound = [];
        $chartOutbound = [];
        $chartShopee = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $dateStr = $date->toDateString();
            $datePrefix = $date->format('ymd');
            $chartLabels[] = $date->translatedFormat('d M');

            $dayData = $mutationAggregates->get($dateStr, collect());

            $chartInbound[] = (int) ($dayData->firstWhere('type', 'inbound')?->total_qty ?? 0);
            $chartOutbound[] = abs((int) ($dayData->firstWhere('type', 'outbound')?->total_qty ?? 0));
            $chartShopee[] = (int) ($shopeeDailyCounts->get($datePrefix) ?? 0);
        }

        $allProducts = Product::where('status', 'active')->select(['id', 'sku', 'name', 'unit', 'stock'])->orderBy('name')->get();
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
            'totalShopeeSales',
            'totalShopeeOrders',
            'releasedShopeeSales',
            'escrowShopeeSales',
            'readyToShipOrders',
            'inTransitOrders',
            'completedShopeeOrders',
            'activePackingOrders',
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
        // 1. Pending Orders awaiting packing (READY_TO_SHIP and PROCESSED)
        $activeStatuses = ['READY_TO_SHIP', 'PROCESSED'];

        $pendingOrders = ShopeeOrder::whereIn('order_status', $activeStatuses)
            ->whereDoesntHave('packingRecords', function ($q) {
                $q->where('status', 'completed');
            })
            ->latest('id')
            ->take(8)
            ->get();

        $pendingCount = ShopeeOrder::whereIn('order_status', $activeStatuses)
            ->whereDoesntHave('packingRecords', function ($q) {
                $q->where('status', 'completed');
            })
            ->count();

        // 2. Buyer Cancellations & Return requests (last 48 hours)
        $cancellations = ShopeeOrder::whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])
            ->where('updated_at', '>=', now()->subHours(48))
            ->latest('updated_at')
            ->take(6)
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
                'status' => $o->order_status,
                'carrier' => $o->shipping_carrier ?? 'Shopee',
                'time_ago' => $o->created_at ? $o->created_at->diffForHumans(['locale' => 'id']) : 'Baru saja',
                'items_count' => count($o->items_data ?? []),
            ]),
            'cancellations_count' => $cancellationsCount,
            'cancellations' => $cancellations->map(fn ($o) => [
                'id' => $o->id,
                'order_sn' => $o->order_sn,
                'tracking_number' => $o->tracking_number,
                'status' => $o->order_status,
                'buyer' => $o->buyer_username,
                'time_ago' => $o->updated_at ? $o->updated_at->diffForHumans(['locale' => 'id']) : 'Baru saja',
            ]),
            'low_stock_count' => $lowStockCount,
        ]);
    }
}
