<?php
// dashboard.php
// 1. ดึงไฟล์ config และเชื่อมต่อฐานข้อมูล (config_mysqli.php จัดการเรื่อง mysqli_report และการเชื่อมต่อแล้ว)
require_once 'config_mysqli.php'; 
// Note: $mysqli object is now available, or the script exited on error.

// ข้อมูลเริ่มต้น
$data = [
    'monthly' => [],
    'category' => [],
    'region' => [],
    'topProducts' => [],
    'payment' => [],
    'hourly' => [],
    'newReturning' => [],
    'kpi' => ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0],
    'error' => null
];

try {
    function q($db, $sql) {
        $res = $db->query($sql);
        // เพิ่มการตรวจสอบผลลัพธ์เพื่อป้องกัน fetch_all() จากค่า null
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // 2. ดึงข้อมูลสำหรับแผนภูมิต่างๆ (ใช้ View ที่สร้างขึ้นใหม่และที่มีอยู่แล้ว)
    $data['monthly'] = q($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
    $data['category'] = q($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
    $data['region'] = q($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
    $data['topProducts'] = q($mysqli, "SELECT product_name, qty_sold FROM v_top_products");
    $data['payment'] = q($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
    $data['hourly'] = q($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
    $data['newReturning'] = q($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

    // 3. ดึงข้อมูล KPI 30 วัน
    $kpi = q($mysqli, "
        SELECT SUM(net_amount) sales_30d, SUM(quantity) qty_30d, COUNT(DISTINCT customer_id) buyers_30d
        FROM fact_sales
        WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    ");
    if ($kpi && !empty($kpi)) $data['kpi'] = $kpi[0];

} catch (Exception $e) {
    // จัดการข้อผิดพลาดในการคิวรี่ฐานข้อมูล
    $data['error'] = 'Database Query Error: ' . $e->getMessage();
}

// 4. Function สำหรับจัดรูปแบบตัวเลข (Number Format)
function nf($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW — Modern Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
/* CSS ปรับปรุง: Dark Theme และความสวยงาม */
body {
    /* ปรับ gradient ให้ดูนุ่มนวลขึ้น */
    background: radial-gradient(circle at 10% 10%, #0f172a, #1e293b);
    color: #f8fafc;
    font-family: 'Prompt', sans-serif;
}
h2 { color: #60a5fa; font-weight: 700; } /* เน้น h2 */
h5 { 
    font-size: 1.25rem; 
    font-weight: 600; 
    color: #f8fafc; 
    border-bottom: 1px solid rgba(255,255,255,0.1); /* เส้นแบ่งใต้หัวข้อ */
    padding-bottom: 0.5rem; 
    margin-bottom: 1rem; 
} 
.card {
    backdrop-filter: blur(10px);
    background: rgba(30, 41, 59, 0.65);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1rem;
    box-shadow: 0 4px 25px rgba(0,0,0,0.3);
}
.kpi-card {
    text-align: center;
    padding: 1.5rem 1rem;
    transition: transform 0.2s;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 30px rgba(0,0,0,0.4);
}
.kpi-title {
    font-size: 1rem;
    font-weight: 500;
    color: #94a3b8; /* สีหัวข้อ KPI */
    margin-bottom: 0.5rem;
}
.kpi-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: #facc15;
    line-height: 1.2;
}
canvas { max-height: 400px; } 
footer { text-align: center; font-size: 0.8rem; color: #64748b; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); }
</style>
</head>
<body class="p-4">

<div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-5">
        <h2><i class="bi bi-speedometer2 me-3"></i>Retail DW Analytics Dashboard</h2>
        
        <div class="d-flex align-items-center">
            <span class="text-secondary small me-3"><i class="bi bi-calendar-check me-1"></i>อัพเดตล่าสุด: <?= date("d M Y") ?></span>
            
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
            </a>
            </div>
    </div>

    <?php if (isset($mysqli) && $mysqli->connect_error): ?>
        <div class="alert alert-danger">Database Connection Error: <?= htmlspecialchars($mysqli->connect_error) ?></div>
    <?php elseif ($data['error']): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($data['error']) ?></div>
    <?php else: ?>
    <div class="row g-4 mb-5">
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-currency-dollar me-2"></i>ยอดขาย 30 วัน</div>
            <div class="kpi-value">฿<?= nf($data['kpi']['sales_30d']) ?></div>
        </div></div>
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-box me-2"></i>จำนวนชิ้นขาย</div>
            <div class="kpi-value"><?= number_format((int)$data['kpi']['qty_30d']) ?> ชิ้น</div>
        </div></div>
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-people-fill me-2"></i>ผู้ซื้อ (30 วัน)</div>
            <div class="kpi-value"><?= number_format((int)$data['kpi']['buyers_30d']) ?> คน</div>
        </div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8"><div class="card p-4">
            <h5><i class="bi bi-graph-up me-2"></i>ยอดขายรายเดือน</h5><canvas id="monthlyChart"></canvas>
        </div></div>
        <div class="col-lg-4"><div class="card p-4">
            <h5><i class="bi bi-tags-fill me-2"></i>ยอดขายตามหมวด</h5><canvas id="categoryChart"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-geo-alt-fill me-2"></i>ยอดขายตามภูมิภาค</h5><canvas id="regionChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
        <h5><i class="bi bi-star-fill me-2"></i>สินค้าขายดี</h5>
    
        <canvas id="topChart" style="height: 400px !important; display: block !important;"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-credit-card-2-front-fill me-2"></i>วิธีการชำระเงิน</h5><canvas id="payChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-clock-fill me-2"></i>ยอดขายรายชั่วโมง</h5><canvas id="hourChart"></canvas>
        </div></div>

        <div class="col-12"><div class="card p-4">
            <h5><i class="bi bi-person-lines-fill me-2"></i>ลูกค้าใหม่ vs ลูกค้าเดิม</h5><canvas id="custChart"></canvas>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<footer>© <?= date("Y") ?> Retail DW Analytics Dashboard. All rights reserved.</footer>

<script>
// JavaScript/Chart.js Configuration (แก้ไข Error โดยการตรวจสอบ Context)
const d = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
const ctx = id => document.getElementById(id);

// ฟังก์ชันสำคัญ: ตรวจสอบว่า Canvas Element มีอยู่และดึง 2D Context ได้หรือไม่
const chartContext = id => ctx(id) ? ctx(id).getContext('2d') : null; 

const toXY = (a, x, y) => ({labels:a.map(o=>o[x]),values:a.map(o=>parseFloat(o[y]))});

// Base Options สำหรับแผนภูมิทั้งหมด
const baseOpt = {
    responsive:true,
    maintainAspectRatio: false,
    plugins:{
        legend:{
            labels:{
                color:'#f1f5f9',
                boxWidth: 15,
                padding: 15
            }
        },
        tooltip:{
            backgroundColor:'rgba(30, 41, 59, 0.9)',
            titleColor: '#facc15',
            bodyColor: '#f1f5f9',
            borderColor: 'rgba(255,255,255,0.2)',
            borderWidth: 1
        }
    },
    scales:{
        x:{
            grid:{ color:'rgba(255,255,255,0.08)' },
            ticks:{ color:'#93c5fd' }
        },
        y:{
            grid:{ color:'rgba(255,255,255,0.08)' },
            ticks:{ color:'#93c5fd' }
        }
    },
    animation:{ duration:1200, easing:'easeOutCubic' }
};

// Monthly Chart (Line)
(() => {
    const context = chartContext('monthlyChart');
    if (!context) return; // แก้ Error: จะไม่พยายามสร้างถ้า Canvas เป็น null
    const {labels,values} = toXY(d.monthly,'ym','net_sales');
    new Chart(context, {
        type:'line',
        data:{ labels, datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            borderColor:'#60a5fa',
            backgroundColor:'rgba(96,165,250,0.35)',
            pointBackgroundColor: '#f8fafc',
            pointBorderColor: '#60a5fa',
            pointRadius: 4,
            fill:true,
            tension:0.4
        }]},
        options:baseOpt
    });
})();

// Category Chart (Doughnut)
(() => {
    const context = chartContext('categoryChart');
    if (!context) return;
    const {labels,values}=toXY(d.category,'category','net_sales');
    new Chart(context, {
        type:'doughnut',
        data:{labels,datasets:[{
            data:values,
            backgroundColor:['#38bdf8','#a78bfa','#f472b6','#34d399','#facc15','#fb7185'],
            hoverOffset: 10
        }]},
        options:{...baseOpt,
            scales:{ x:{display:false}, y:{display:false} },
            plugins:{...baseOpt.plugins,legend:{position:'right', labels:{color:'#f1f5f9'}}}
        }
    });
})();

// ==========================================================
// VVVV --- นี่คือบล็อกที่แก้ไขปัญหาแล้ว --- VVVV
// ==========================================================
// Top products Chart (Horizontal Bar)
/// Top products Chart (Horizontal Bar)
(() => {
    const context = chartContext('topChart');
    if (!context) return;
    const labels=d.topProducts.map(o=>o.product_name);
    
    // (โค้ดแก้ปัญหาชาร์ตไม่ขึ้น จากครั้งที่แล้ว)
    const vals=d.topProducts.map(o=>parseFloat(o.qty_sold) || 0); 

    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ชิ้นขาย',
            data:vals,
            // ===== START: แก้ไขสีชาร์ต (Kitty Theme) =====
            backgroundColor:'#e11d48', /* "Kitty Bow" Red */
            // ===== END: แก้ไขสีชาร์ต =====
            borderRadius: 5
        }]},
        // ===== START: แก้ไข =====
        // ลบ indexAxis: 'y' ออก เพื่อให้เป็นกราฟแนวตั้ง (Vertical Bar)
        // ซึ่งจะเข้ากับ baseOpt และชาร์ตอื่นๆ
        options:baseOpt 
        // ===== END: แก้ไข =====
    });
})();


// Region Chart (Bar)
(() => {
    const context = chartContext('regionChart');
    if (!context) return;
    const {labels,values}=toXY(d.region,'region','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor:'#a78bfa',
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// Payment Chart (Pie)
(() => {
    const context = chartContext('payChart');
    if (!context) return;
    const {labels,values}=toXY(d.payment,'payment_method','net_sales');
    new Chart(context, {
        type:'pie',
        data:{labels,datasets:[{
            data:values,
            backgroundColor:['#fb923c','#3b82f6','#10b981','#f59e0b'],
            hoverOffset: 10
        }]},
        options:{...baseOpt,
            scales:{ x:{display:false}, y:{display:false} },
            plugins:{...baseOpt.plugins,legend:{position:'right', labels:{color:'#f1f5f9'}}}
        }
    });
})();

// Hourly Chart (Bar)
(() => {
    const context = chartContext('hourChart');
    if (!context) return;
    const {labels,values}=toXY(d.hourly,'hour_of_day','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor:'#f472b6',
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// New vs Returning Chart (Line)
(() => {
    const context = chartContext('custChart');
    if (!context) return;
    const labels=d.newReturning.map(o=>o.date_key);
    const n=d.newReturning.map(o=>parseFloat(o.new_customer_sales));
    const r=d.newReturning.map(o=>parseFloat(o.returning_sales));
    new Chart(context,{
        type:'line',
        data:{labels,datasets:[
            {label:'ลูกค้าใหม่',data:n,borderColor:'#facc15',tension:0.4, fill:false, pointRadius: 3},
            {label:'ลูกค้าเดิม',data:r,borderColor:'#38bdf8',tension:0.4, fill:false, pointRadius: 3}
        ]},
        options:baseOpt
    });
})();
</script>
</body>
</html>