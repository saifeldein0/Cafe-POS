// Demo data used only for the guide screenshots (run via: php artisan tinker --execute="$(cat docs/guide-src/demo-data.php)").
// Reset afterwards with: php artisan migrate:fresh --seed
mt_srand(42);
$p = app(App\Services\PurchaseService::class);
$o = app(App\Services\OrderService::class);
App\Models\Shift::current() ?? app(App\Services\ShiftService::class)->start(); // orders need an open shift
// October stock-up purchases: [ingredient, packages, cost, day]
foreach ([[1,8,240,1],[2,400,20,1],[3,6,43.2,1],[4,10,33,1],[5,8,33.6,1],[6,6,50.4,1],[7,4,67.2,1],[8,3,93.6,1],[9,150,105,1],[10,80,128,1],[11,4,20,1],[1,6,180,15],[3,6,43.2,15],[9,150,105,15],[10,60,96,15]] as [$i,$pk,$c,$d]) {
  $p->create(['ingredient_id'=>$i,'packages'=>$pk,'total_cost'=>$c,'purchased_at'=>"2026-10-".sprintf('%02d',$d)]);
}
$weights = [1=>30,2=>8,3=>10,4=>4,5=>3,6=>2,7=>8,8=>4,9=>5,10=>4,11=>4,12=>2,13=>2,14=>2,15=>6,16=>4,17=>3,18=>1];
$pick = function() use ($weights) { $r = mt_rand(1, array_sum($weights)); foreach ($weights as $id=>$w) { $r -= $w; if ($r <= 0) return $id; } return 1; };
$mk = function($ts) use ($o, $pick) { $cart=[]; $n=mt_rand(1,3); for($k=0;$k<$n;$k++){ $id=$pick(); $cart[$id]=($cart[$id]??0)+mt_rand(1,2);} $ord=$o->create($cart); $ord->created_at=$ts; $ord->updated_at=$ts; $ord->saveQuietly(); };
for ($d=1; $d<=31; $d++) { $cnt = mt_rand(40,50); for ($j=0;$j<$cnt;$j++) { $mk(Carbon\Carbon::create(2026,10,$d, mt_rand(7,21), mt_rand(0,59), 0, 'Africa/Tunis')); } }
for ($d=1; $d<=4; $d++) { $cnt = mt_rand(40,50); for ($j=0;$j<$cnt;$j++) { $mk(Carbon\Carbon::create(2026,11,$d, mt_rand(7,21), mt_rand(0,59), 0, 'Africa/Tunis')); } }
for ($j=0;$j<10;$j++) { $mk(now()->setTime(mt_rand(7, max(7, (int)now()->format('H'))), mt_rand(0,59))->min(now())); }
foreach (App\Models\Ingredient::all() as $ing) {
  if ($ing->name === 'Gazouz' || (float)$ing->current_stock > 0) continue;
  $need = -(float)$ing->current_stock * 1.25 + (float)$ing->units_per_package * 2;
  $pk = ceil($need / (float)$ing->units_per_package);
  $p->create(['ingredient_id'=>$ing->id,'packages'=>$pk,'total_cost'=>round($pk*(float)$ing->units_per_package*(float)$ing->last_unit_cost,1),'purchased_at'=>'2026-10-20']);
}
$p->create(['ingredient_id'=>1,'packages'=>1,'total_cost'=>30,'purchased_at'=>'2026-11-05']);
$p->create(['ingredient_id'=>4,'packages'=>1,'total_cost'=>3.3,'purchased_at'=>'2026-11-05']);
$p->create(['ingredient_id'=>3,'packages'=>1,'total_cost'=>7.2,'purchased_at'=>'2026-11-05']);
$r = App\Services\MonthlyReport::for(2026,10);
echo 'oct sales '.$r->totalSales().' purch '.$r->purchasesTotal().' net '.$r->netProfit().PHP_EOL;
$r = App\Services\MonthlyReport::current();
echo 'nov sales '.$r->totalSales().' net '.$r->netProfit().PHP_EOL;
echo App\Models\Ingredient::where('current_stock','<=',0)->count().' out; orders '.App\Models\Order::count().' today '.App\Models\Order::whereDate('created_at', today())->count().' todaysum '.App\Models\Order::whereDate('created_at', today())->sum('total');
