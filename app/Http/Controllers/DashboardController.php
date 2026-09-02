<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $totalStock = (int) Product::sum('stock');
        $totalAssetValue = Product::selectRaw('SUM(stock * purchase_price) as total_val')->value('total_val') ?? 0;
        $lowStockCount = Product::whereColumn('stock', '<=', 'safety_stock')->count();
        $todayMutationsCount = StockMutation::whereDate('created_at', today())->count();

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
            'shopeeSetting'
        ));
    }
}
