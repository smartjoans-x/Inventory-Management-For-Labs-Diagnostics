<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SL Diagnostics — Laboratory Inventory &amp; Stock Management System</title>
<meta name="description" content="Core PHP + MySQL laboratory inventory and stock management system: two-stage Order → Receive (GRN) purchase workflow, lot-wise valuation, FIFO issue vouchers and a live monthly budget. Built by SLDPL / SmartJOANS.">
<meta name="theme-color" content="#070a12">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='78' font-size='78' font-family='Times New Roman' font-weight='bold' fill='%232ee6c5'%3ESL%3C/text%3E%3C/svg%3E">
<style>
/* ============================================================
   SL Diagnostics — Inventory & Stock Management
   Single file. No CDN. No build step.
   ============================================================ */
*,*::before,*::after{box-sizing:border-box}
:root{
  --bg:#070a12;
  --bg-soft:#0b1020;
  --card:rgba(255,255,255,.042);
  --card-2:rgba(255,255,255,.06);
  --line:rgba(255,255,255,.10);
  --line-soft:rgba(255,255,255,.06);
  --txt:#e9eefc;
  --mut:#95a2c0;
  --dim:#6d7a97;
  --cy:#2ee6c5;
  --bl:#5b93ff;
  --vi:#a97bff;
  --am:#ffb84d;
  --rose:#ff7a8a;
  --grad:linear-gradient(100deg,var(--cy),var(--bl) 48%,var(--vi));
  --r:18px;
  --shadow:0 24px 60px -24px rgba(0,0,0,.85);
  --mono:ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,"Liberation Mono",monospace;
  --sans:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  margin:0;background:var(--bg);color:var(--txt);font-family:var(--sans);
  line-height:1.65;font-size:16px;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
}
img{max-width:100%}
a{color:inherit}
::selection{background:rgba(46,230,197,.28)}

/* ---------- animated backdrop ---------- */
.bgfx{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.bgfx .grid{
  position:absolute;inset:-2px;
  background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);
  background-size:58px 58px;
  -webkit-mask-image:radial-gradient(110% 80% at 50% 0%,#000 0%,transparent 72%);
          mask-image:radial-gradient(110% 80% at 50% 0%,#000 0%,transparent 72%);
}
.blob{position:absolute;border-radius:50%;filter:blur(90px);opacity:.42;will-change:transform}
.b1{width:620px;height:620px;background:#0e6d7a;top:-230px;left:-160px;animation:float1 22s ease-in-out infinite}
.b2{width:560px;height:560px;background:#2b3fa8;top:12%;right:-200px;animation:float2 26s ease-in-out infinite}
.b3{width:520px;height:520px;background:#5b2c93;bottom:4%;left:8%;animation:float3 30s ease-in-out infinite}
@keyframes float1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(90px,70px) scale(1.12)}}
@keyframes float2{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-80px,100px) scale(1.08)}}
@keyframes float3{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(70px,-80px) scale(1.14)}}

/* ---------- layout ---------- */
.wrap{position:relative;z-index:1}
.container{width:min(1140px,92vw);margin-inline:auto}
section{padding:92px 0;position:relative}
@media(max-width:720px){section{padding:64px 0}}

/* ---------- nav ---------- */
header.nav{
  position:sticky;top:0;z-index:50;
  background:rgba(7,10,18,.72);backdrop-filter:blur(16px) saturate(140%);
  -webkit-backdrop-filter:blur(16px) saturate(140%);
  border-bottom:1px solid var(--line-soft);
}
.nav-in{display:flex;align-items:center;gap:18px;height:62px}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.2px;text-decoration:none;flex:0 0 auto}
.brand .mark{
  width:34px;height:34px;border-radius:10px;display:grid;place-items:center;
  background:var(--grad);color:#05121a;font-family:"Times New Roman",Times,serif;font-weight:bold;font-size:15px;
  box-shadow:0 6px 18px -6px rgba(46,230,197,.7)
}
.brand .wordmark{font-family:"Times New Roman",Times,serif;font-size:18px}
.brand .wordmark b{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.nav-links{display:flex;gap:4px;margin-left:auto;overflow-x:auto;scrollbar-width:none}
.nav-links::-webkit-scrollbar{display:none}
.nav-links a{
  text-decoration:none;color:var(--mut);font-size:13.5px;padding:7px 12px;border-radius:999px;
  white-space:nowrap;transition:.25s;border:1px solid transparent
}
.nav-links a:hover{color:var(--txt);background:var(--card)}
.nav-links a.on{color:#06121a;background:var(--grad);font-weight:600}
.progress{position:absolute;left:0;bottom:-1px;height:2px;width:0;background:var(--grad);box-shadow:0 0 12px rgba(46,230,197,.7)}
@media(max-width:860px){.nav-links{gap:2px}.nav-links a{padding:6px 10px;font-size:12.5px}}
@media(max-width:560px){.brand .wordmark{display:none}}

/* ---------- reveal ---------- */
.rv{opacity:0;transform:translateY(26px);transition:opacity .75s cubic-bezier(.22,1,.36,1),transform .75s cubic-bezier(.22,1,.36,1)}
.rv.in{opacity:1;transform:none}
.rv[data-d="1"]{transition-delay:.07s}.rv[data-d="2"]{transition-delay:.14s}
.rv[data-d="3"]{transition-delay:.21s}.rv[data-d="4"]{transition-delay:.28s}
.rv[data-d="5"]{transition-delay:.35s}.rv[data-d="6"]{transition-delay:.42s}
.rv[data-d="7"]{transition-delay:.49s}.rv[data-d="8"]{transition-delay:.56s}

/* ---------- hero ---------- */
.hero{padding:74px 0 56px;text-align:center}
.pill{
  display:inline-flex;align-items:center;gap:9px;padding:7px 15px;border-radius:999px;
  border:1px solid var(--line);background:var(--card);font-size:12.5px;color:var(--mut);
  letter-spacing:.4px;text-transform:uppercase
}
.dot{width:7px;height:7px;border-radius:50%;background:var(--cy);box-shadow:0 0 0 0 rgba(46,230,197,.65);animation:pulse 2.2s infinite}
@keyframes pulse{70%{box-shadow:0 0 0 11px rgba(46,230,197,0)}100%{box-shadow:0 0 0 0 rgba(46,230,197,0)}}
h1{
  font-size:clamp(2.1rem,6.2vw,4.1rem);line-height:1.06;margin:22px 0 0;letter-spacing:-1.6px;font-weight:800
}
h1 .g{
  background:linear-gradient(100deg,var(--cy),var(--bl) 40%,var(--vi) 75%,var(--cy));
  background-size:260% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;
  animation:shimmer 7s linear infinite
}
@keyframes shimmer{to{background-position:260% 0}}
.lead{max-width:760px;margin:20px auto 0;color:var(--mut);font-size:clamp(1rem,2.2vw,1.16rem)}
.chips{display:flex;flex-wrap:wrap;gap:9px;justify-content:center;margin-top:26px}
.chip{
  font-size:12.5px;padding:6px 13px;border-radius:999px;border:1px solid var(--line);
  background:var(--card);color:var(--mut);transition:.25s
}
.chip:hover{color:var(--txt);border-color:rgba(46,230,197,.45);transform:translateY(-2px)}
.cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:32px}
.btn{
  display:inline-flex;align-items:center;gap:9px;text-decoration:none;font-weight:600;font-size:14.5px;
  padding:12px 22px;border-radius:12px;transition:.25s;border:1px solid var(--line)
}
.btn.primary{background:var(--grad);color:#05121a;border-color:transparent;box-shadow:0 14px 34px -14px rgba(46,230,197,.75)}
.btn.primary:hover{transform:translateY(-2px);box-shadow:0 20px 42px -14px rgba(46,230,197,.9)}
.btn.ghost{background:var(--card);color:var(--txt)}
.btn.ghost:hover{background:var(--card-2);transform:translateY(-2px)}

/* ---------- terminal ---------- */
.term{
  max-width:620px;margin:40px auto 0;text-align:left;border:1px solid var(--line);border-radius:14px;
  background:linear-gradient(180deg,rgba(13,18,32,.95),rgba(8,11,20,.95));box-shadow:var(--shadow);overflow:hidden
}
.term-bar{display:flex;align-items:center;gap:7px;padding:10px 14px;border-bottom:1px solid var(--line-soft);background:rgba(255,255,255,.025)}
.tdot{width:10px;height:10px;border-radius:50%}
.tdot.r{background:#ff5f57}.tdot.y{background:#febc2e}.tdot.g{background:#28c840}
.term-title{margin-left:8px;font-size:11.5px;color:var(--dim);font-family:var(--mono)}
.term-body{padding:16px 18px;font-family:var(--mono);font-size:13px;line-height:2;min-height:112px}
.term-body .ln{white-space:nowrap;overflow:hidden}
.term-body .ps{color:var(--cy)}
.term-body .ok{color:var(--dim)}
.caret{display:inline-block;width:8px;height:15px;background:var(--cy);vertical-align:-2px;animation:blink 1s steps(1) infinite}
@keyframes blink{50%{opacity:0}}

/* ---------- stats ---------- */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-top:52px}
.stat{
  border:1px solid var(--line);background:var(--card);border-radius:var(--r);padding:20px 16px;text-align:center;
  position:relative;overflow:hidden;transition:.3s
}
.stat::after{content:"";position:absolute;inset:0;background:var(--grad);opacity:0;transition:.3s}
.stat:hover{transform:translateY(-4px);border-color:rgba(46,230,197,.4)}
.stat .n{font-size:clamp(1.5rem,3.6vw,2rem);font-weight:800;letter-spacing:-.8px;
  background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat .l{font-size:11.5px;color:var(--dim);text-transform:uppercase;letter-spacing:1px;margin-top:4px}

/* ---------- headings ---------- */
.sec-head{max-width:760px;margin-bottom:44px}
.sec-head.center{margin-inline:auto;text-align:center}
.kicker{
  display:inline-block;font-size:11.5px;letter-spacing:2px;text-transform:uppercase;color:var(--cy);
  font-weight:700;margin-bottom:12px
}
h2{font-size:clamp(1.6rem,3.6vw,2.4rem);line-height:1.18;margin:0;letter-spacing:-1px;font-weight:800}
.sec-head p{color:var(--mut);margin:14px 0 0;font-size:clamp(.98rem,2vw,1.05rem)}
h3{font-size:1.1rem;margin:0 0 8px;font-weight:700;letter-spacing:-.2px}

/* ---------- cards ---------- */
.grid{display:grid;gap:16px}
.g3{grid-template-columns:repeat(auto-fit,minmax(290px,1fr))}
.g2{grid-template-columns:repeat(auto-fit,minmax(340px,1fr))}
.card{
  border:1px solid var(--line);background:var(--card);border-radius:var(--r);padding:24px;position:relative;
  overflow:hidden;transition:transform .35s cubic-bezier(.22,1,.36,1),border-color .35s,background .35s
}
.card::before{
  content:"";position:absolute;inset:0;border-radius:var(--r);padding:1px;opacity:0;transition:.35s;
  background:var(--grad);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none
}
.card:hover{transform:translateY(-5px);background:var(--card-2)}
.card:hover::before{opacity:.75}
.card p{color:var(--mut);font-size:14.5px;margin:0}
.ico{
  width:42px;height:42px;border-radius:12px;display:grid;place-items:center;margin-bottom:16px;
  background:linear-gradient(145deg,rgba(46,230,197,.16),rgba(169,123,255,.16));border:1px solid var(--line)
}
.ico svg{width:21px;height:21px;stroke:var(--cy);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* ---------- workflow ---------- */
.flow{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:stretch;gap:0;margin-top:10px}
.flow .step{
  border:1px solid var(--line);background:var(--card);border-radius:var(--r);padding:22px;position:relative;overflow:hidden
}
.flow .step .num{
  position:absolute;top:14px;right:16px;font-size:2.6rem;font-weight:800;color:rgba(255,255,255,.05);line-height:1
}
.badge{display:inline-block;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;font-weight:700;
  padding:4px 10px;border-radius:999px;margin-bottom:12px}
.badge.draft{background:rgba(255,184,77,.14);color:var(--am);border:1px solid rgba(255,184,77,.3)}
.badge.recv{background:rgba(91,147,255,.14);color:var(--bl);border:1px solid rgba(91,147,255,.3)}
.badge.final{background:rgba(46,230,197,.14);color:var(--cy);border:1px solid rgba(46,230,197,.32)}
.conn{position:relative;width:58px;display:flex;align-items:center;justify-content:center}
.conn .line{height:2px;width:100%;background:linear-gradient(90deg,rgba(46,230,197,.15),rgba(169,123,255,.5));position:relative;overflow:hidden}
.conn .line::after{
  content:"";position:absolute;top:-3px;left:-20%;width:20%;height:8px;border-radius:50%;
  background:var(--cy);filter:blur(3px);animation:travel 2.6s linear infinite
}
@keyframes travel{to{left:120%}}
@media(max-width:900px){
  .flow{grid-template-columns:1fr}
  .conn{width:100%;height:44px}
  .conn .line{width:2px;height:100%;background:linear-gradient(180deg,rgba(46,230,197,.15),rgba(169,123,255,.5))}
  .conn .line::after{top:-20%;left:-3px;width:8px;height:20%;animation:travelY 2.6s linear infinite}
  @keyframes travelY{to{top:120%}}
}
.flow ul,.mini-list{margin:12px 0 0;padding-left:18px;color:var(--mut);font-size:13.8px}
.flow li,.mini-list li{margin:6px 0}

/* ---------- variance bars ---------- */
.bars{margin-top:18px}
.bar-row{margin:14px 0}
.bar-top{display:flex;justify-content:space-between;font-size:13px;color:var(--mut);margin-bottom:7px}
.bar-top b{color:var(--txt);font-family:var(--mono)}
.track{height:12px;border-radius:999px;background:rgba(255,255,255,.06);overflow:hidden}
.fill{height:100%;width:0;border-radius:999px;transition:width 1.4s cubic-bezier(.22,1,.36,1)}
.fill.order{background:linear-gradient(90deg,rgba(255,184,77,.75),var(--am))}
.fill.recv{background:linear-gradient(90deg,rgba(46,230,197,.7),var(--cy))}

/* ---------- code ---------- */
.code{
  position:relative;border:1px solid var(--line);background:rgba(5,8,16,.75);border-radius:14px;
  font-family:var(--mono);font-size:13px;line-height:1.85;overflow:auto;margin:14px 0 0
}
.code pre{margin:0;padding:16px 18px;white-space:pre}
.code .c{color:var(--dim)}
.code .k{color:var(--vi)}
.code .s{color:var(--cy)}
.code .f{color:var(--am)}
.copy{
  position:absolute;top:9px;right:9px;border:1px solid var(--line);background:rgba(255,255,255,.05);
  color:var(--mut);font-size:11px;padding:5px 10px;border-radius:8px;cursor:pointer;transition:.2s;font-family:var(--sans)
}
.copy:hover{color:var(--txt);background:rgba(255,255,255,.1)}
.copy.done{color:var(--cy);border-color:rgba(46,230,197,.45)}

/* ---------- steps (install) ---------- */
.steps{position:relative;padding-left:46px}
.steps::before{content:"";position:absolute;left:17px;top:8px;bottom:8px;width:2px;background:rgba(255,255,255,.08)}
.steps .fillline{position:absolute;left:17px;top:8px;width:2px;height:0;background:var(--grad);transition:height 1.2s ease;box-shadow:0 0 12px rgba(46,230,197,.6)}
.step-i{position:relative;padding-bottom:30px}
.step-i:last-child{padding-bottom:0}
.step-i .bullet{
  position:absolute;left:-46px;top:0;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;
  font-weight:700;font-size:14px;background:var(--bg-soft);border:1px solid var(--line);color:var(--mut);transition:.4s
}
.step-i.in .bullet{background:var(--grad);color:#05121a;border-color:transparent;box-shadow:0 8px 22px -8px rgba(46,230,197,.8)}
@media(max-width:560px){.steps{padding-left:38px}.steps::before,.steps .fillline{left:13px}.step-i .bullet{left:-38px;width:28px;height:28px;font-size:12.5px}}

/* ---------- tables ---------- */
.tbl-wrap{border:1px solid var(--line);border-radius:var(--r);overflow:auto;background:var(--card)}
table{width:100%;border-collapse:collapse;font-size:14px;min-width:520px}
th,td{padding:13px 16px;text-align:left;border-bottom:1px solid var(--line-soft)}
th{font-size:11.5px;letter-spacing:1.1px;text-transform:uppercase;color:var(--dim);font-weight:700;background:rgba(255,255,255,.025)}
tr:last-child td{border-bottom:none}
tbody tr{transition:background .25s}
tbody tr:hover{background:rgba(255,255,255,.028)}
td.num,th.num{text-align:right;font-family:var(--mono)}
.yes{color:var(--cy);font-weight:700}
.no{color:var(--rose);font-weight:700}
td code,p code,li code{font-family:var(--mono);font-size:12.6px;background:rgba(255,255,255,.07);padding:2px 7px;border-radius:6px;color:var(--cy)}

/* ---------- tree ---------- */
.tree{font-family:var(--mono);font-size:13px;line-height:1.95;color:var(--mut);white-space:pre;overflow:auto;margin:0;padding:22px}
.tree .d{color:var(--bl);font-weight:600}
.tree .f{color:var(--txt)}
.tree .c{color:var(--dim)}
.tree .hot{color:var(--cy)}

/* ---------- chain (budget) ---------- */
.chain{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:20px}
.node{
  flex:1 1 150px;border:1px solid var(--line);background:var(--card);border-radius:14px;padding:16px;text-align:center;transition:.3s
}
.node:hover{transform:translateY(-4px);border-color:rgba(46,230,197,.4)}
.node .t{font-size:11.5px;text-transform:uppercase;letter-spacing:1px;color:var(--dim)}
.node .v{font-family:var(--mono);font-size:1.02rem;font-weight:700;margin-top:6px;color:var(--txt)}
.arw{color:var(--dim);font-size:18px;flex:0 0 auto}
@media(max-width:720px){.arw{display:none}}

/* ---------- fifo ---------- */
.lots{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;align-items:center}
.lot{
  border:1px solid var(--line);border-radius:12px;padding:11px 14px;background:var(--card);min-width:116px;
  transition:.3s;position:relative
}
.lot .ln{font-family:var(--mono);font-size:12.5px;color:var(--cy)}
.lot .lq{font-size:12px;color:var(--dim);margin-top:2px}
.lot.first{border-color:rgba(46,230,197,.5);box-shadow:0 0 0 3px rgba(46,230,197,.08)}
.lot.first::after{content:"FIFO";position:absolute;top:-9px;right:8px;font-size:9.5px;letter-spacing:1px;
  background:var(--cy);color:#05121a;padding:1px 6px;border-radius:999px;font-weight:700}

/* ---------- security chips ---------- */
.sec-chips{display:flex;flex-wrap:wrap;gap:9px;margin-top:4px}
.sc{
  display:inline-flex;align-items:center;gap:7px;font-size:12.6px;color:var(--mut);padding:7px 13px;
  border-radius:999px;border:1px solid var(--line);background:var(--card);transition:.25s
}
.sc:hover{color:var(--txt);border-color:rgba(46,230,197,.45);transform:translateY(-2px)}
.sc svg{width:13px;height:13px;stroke:var(--cy);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

/* ---------- callout ---------- */
.note{
  border:1px solid rgba(255,184,77,.28);background:linear-gradient(135deg,rgba(255,184,77,.08),rgba(255,184,77,.02));
  border-radius:14px;padding:16px 18px;display:flex;gap:13px;align-items:flex-start;margin-top:18px
}
.note svg{width:19px;height:19px;stroke:var(--am);fill:none;stroke-width:2;flex:0 0 auto;margin-top:2px;stroke-linecap:round}
.note p{margin:0;font-size:14px;color:var(--mut)}
.note b{color:var(--am)}

/* ---------- creator ---------- */
.creator{
  border:1px solid var(--line);border-radius:26px;padding:clamp(28px,5vw,52px);
  background:linear-gradient(150deg,rgba(46,230,197,.09),rgba(91,147,255,.05) 45%,rgba(169,123,255,.09));
  position:relative;overflow:hidden
}
.creator::before{
  content:"";position:absolute;width:360px;height:360px;border-radius:50%;background:rgba(46,230,197,.14);
  filter:blur(80px);top:-150px;right:-90px;animation:float2 20s ease-in-out infinite
}
.avatar{
  width:76px;height:76px;border-radius:22px;display:grid;place-items:center;margin:0 auto 18px;
  background:var(--grad);color:#05121a;font-family:"Times New Roman",Times,serif;font-weight:bold;font-size:28px;
  box-shadow:0 18px 44px -16px rgba(46,230,197,.85);animation:breathe 4.5s ease-in-out infinite
}
@keyframes breathe{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.sites{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:13px;margin-top:30px}
.site{
  display:flex;align-items:center;gap:13px;text-decoration:none;border:1px solid var(--line);background:rgba(7,10,18,.45);
  border-radius:15px;padding:15px 17px;transition:.3s
}
.site:hover{transform:translateY(-4px);border-color:rgba(46,230,197,.5);background:rgba(7,10,18,.7)}
.site .si{
  width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:0 0 auto;
  background:rgba(255,255,255,.06);border:1px solid var(--line)
}
.site .si svg{width:18px;height:18px;stroke:var(--cy);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.site .st{font-weight:700;font-size:14.5px}
.site .sd{font-size:12.3px;color:var(--dim);font-family:var(--mono)}
.site .go{margin-left:auto;color:var(--dim);transition:.3s}
.site:hover .go{color:var(--cy);transform:translate(3px,-3px)}
.ig{
  display:inline-flex;align-items:center;gap:10px;text-decoration:none;margin-top:26px;padding:11px 20px;border-radius:999px;
  border:1px solid var(--line);background:rgba(7,10,18,.5);font-weight:600;font-size:14px;transition:.3s
}
.ig:hover{transform:translateY(-3px);border-color:rgba(169,123,255,.6);box-shadow:0 14px 34px -16px rgba(169,123,255,.8)}
.ig svg{width:18px;height:18px;stroke:var(--vi);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* ---------- footer ---------- */
footer{border-top:1px solid var(--line-soft);padding:34px 0;text-align:center;color:var(--dim);font-size:13.2px}
footer a{color:var(--mut);text-decoration:none;transition:.2s}
footer a:hover{color:var(--cy)}
.top{
  position:fixed;right:18px;bottom:18px;width:44px;height:44px;border-radius:50%;display:grid;place-items:center;
  border:1px solid var(--line);background:rgba(11,16,32,.85);backdrop-filter:blur(10px);cursor:pointer;z-index:60;
  opacity:0;pointer-events:none;transition:.3s
}
.top.show{opacity:1;pointer-events:auto}
.top:hover{border-color:rgba(46,230,197,.6);transform:translateY(-3px)}
.top svg{width:17px;height:17px;stroke:var(--cy);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

@media (prefers-reduced-motion:reduce){
  *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}
  .rv{opacity:1;transform:none}
  html{scroll-behavior:auto}
}
</style>
</head>
<body>

<div class="bgfx">
  <div class="grid"></div>
  <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
</div>

<div class="wrap">

<!-- ================= NAV ================= -->
<header class="nav">
  <div class="container nav-in">
    <a class="brand" href="#top">
      <span class="mark">SL</span>
      <span class="wordmark">SL <b>Diagnostics</b></span>
    </a>
    <nav class="nav-links">
      <a href="#overview">Overview</a>
      <a href="#install">Install</a>
      <a href="#workflow">Workflow</a>
      <a href="#stock">Stock Logic</a>
      <a href="#budget">Budget</a>
      <a href="#roles">Roles</a>
      <a href="#files">Files</a>
      <a href="#creator">Creator</a>
    </nav>
  </div>
  <div class="progress" id="prog"></div>
</header>

<!-- ================= HERO ================= -->
<section class="hero" id="top" style="padding-top:74px">
  <div class="container">
    <span class="pill rv in"><span class="dot"></span> Build v6 · Order → Receive (GRN) · Live</span>

    <h1 class="rv" data-d="1">Laboratory <span class="g">Inventory &amp; Stock</span><br>Management System</h1>

    <p class="lead rv" data-d="2">
      Core PHP (PDO) + MySQL. No framework, no composer, no build step — drop it into
      <code style="font-family:var(--mono);font-size:.88em;color:var(--cy)">htdocs</code> or
      <code style="font-family:var(--mono);font-size:.88em;color:var(--cy)">public_html</code> and it runs.
      Lot-wise valuation, a two-stage purchase workflow, FIFO issue vouchers and your Excel budget — living inside the system.
    </p>

    <div class="chips rv" data-d="3">
      <span class="chip">PHP 7.4+ · tested on 8.3</span>
      <span class="chip">MySQL · PDO</span>
      <span class="chip">Bootstrap 5</span>
      <span class="chip">Vanilla JS</span>
      <span class="chip">XAMPP</span>
      <span class="chip">cPanel / LiteSpeed</span>
      <span class="chip">11 tables + 1 view</span>
    </div>

    <div class="cta rv" data-d="4">
      <a class="btn primary" href="#install">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 12 5 5 5-5"/><path d="M5 21h14"/></svg>
        Install in 5 steps
      </a>
      <a class="btn ghost" href="#workflow">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h10"/><path d="m11 4 3 3-3 3"/><path d="M20 17H10"/><path d="m13 14-3 3 3 3"/></svg>
        See the PO workflow
      </a>
    </div>

    <!-- terminal -->
    <div class="term rv" data-d="5">
      <div class="term-bar">
        <span class="tdot r"></span><span class="tdot y"></span><span class="tdot g"></span>
        <span class="term-title">sl-inventory — setup</span>
      </div>
      <div class="term-body" id="term">
        <div class="ln"><span class="ps">$</span> <span class="t1"></span></div>
        <div class="ln"><span class="ps">$</span> <span class="t2"></span></div>
        <div class="ln ok"><span class="t3"></span></div>
      </div>
    </div>

    <!-- stats -->
    <div class="stats">
      <div class="stat rv" data-d="1"><div class="n" data-count="220">0</div><div class="l">Items</div></div>
      <div class="stat rv" data-d="2"><div class="n" data-count="33">0</div><div class="l">Suppliers</div></div>
      <div class="stat rv" data-d="3"><div class="n" data-count="20">0</div><div class="l">Analysers</div></div>
      <div class="stat rv" data-d="4"><div class="n" data-count="9">0</div><div class="l">Departments</div></div>
      <div class="stat rv" data-d="5"><div class="n" data-count="108">0</div><div class="l">Opening lots</div></div>
      <div class="stat rv" data-d="6"><div class="n" data-count="788810.53" data-prefix="₹" data-dec="2">0</div><div class="l">Opening value</div></div>
    </div>
  </div>
</section>

<!-- ================= OVERVIEW ================= -->
<section id="overview">
  <div class="container">
    <div class="sec-head center rv">
      <span class="kicker">What's inside</span>
      <h2>One system from purchase order to consumption</h2>
      <p>Clean database, Admin / User logins, your logo on the printed PO, and the two-stage
         Order → Receive (GRN) purchase workflow — stock is created only after goods are physically checked in.</p>
    </div>

    <div class="grid g3">
      <div class="card rv" data-d="1">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M3 7h18"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13"/></svg></div>
        <h3>Item &amp; supplier masters</h3>
        <p>220 items, each carrying its department, analyser, default supplier and item code. Lot tracking is a per-item switch — reagents on, gloves off.</p>
      </div>
      <div class="card rv" data-d="2">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg></div>
        <h3>Two-stage purchase</h3>
        <p>Order raises a <b>Draft</b> only. Receiving records what actually arrived — quantity, hiked rate, lot numbers, expiry — then Finalize posts the stock.</p>
      </div>
      <div class="card rv" data-d="3">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 3-4 4 3 5-7"/></svg></div>
        <h3>Exact valuation</h3>
        <p>No average costing. Each lot keeps the rate it was bought at, so the same item from three suppliers at three rates still values correctly.</p>
      </div>
      <div class="card rv" data-d="4">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M5 8h14"/><path d="M7 21h10"/><path d="M8 8c0 4-2 5-2 7a3 3 0 0 0 6 0c0-2-2-3-2-7"/></svg></div>
        <h3>Direct GRN</h3>
        <p>Urgent supply, free replacement, sample or an over-the-counter buy — goods received with no purchase order, posted to stock in one screen.</p>
      </div>
      <div class="card rv" data-d="5">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 13h6"/><path d="M9 17h4"/></svg></div>
        <h3>Issue vouchers</h3>
        <p>One slip, many items, FIFO or a lot you pick. All-or-nothing saving — a short line rolls the whole voucher back. Prints with lot numbers and signatures.</p>
      </div>
      <div class="card rv" data-d="6">
        <div class="ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 9v12"/></svg></div>
        <h3>Monthly budget</h3>
        <p>Your Excel sheet, live: opening → purchase → issues → closing → requirement, grouped dept / equipment or supplier wise, with draft POs in one click.</p>
      </div>
      <div class="card rv" data-d="7">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
        <h3>Locked down</h3>
        <p>bcrypt hashes, CSRF on every form, prepared statements everywhere, server-side guards, idle timeout and a 5-attempt lockout.</p>
      </div>
      <div class="card rv" data-d="8">
        <div class="ico"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15a0 0 0 0 1 0 0H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v6h6"/><path d="M8 15h8"/><path d="M8 11h3"/></svg></div>
        <h3>Print &amp; export</h3>
        <p>A4 purchase order with your logo and goods-received comparison, issue slips, and CSV export from the stock report and the budget.</p>
      </div>
      <div class="card rv" data-d="8">
        <div class="ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
        <h3>Full audit trail</h3>
        <p>Who created the PO, who finalized it, who issued each line — names survive the deletion of that login and print under “Prepared By”.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= INSTALL ================= -->
<section id="install">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 02</span>
      <h2>Installation</h2>
      <p>Copy, import, edit four constants, log in. Requires PHP 7.4+ (tested on PHP 8.3) with <code>pdo_mysql</code>.</p>
    </div>

    <div class="steps" id="steps">
      <div class="fillline" id="fillline"></div>

      <div class="step-i rv">
        <div class="bullet">1</div>
        <h3>Drop the folder in place</h3>
        <p style="color:var(--mut);font-size:14.5px;margin:0">
          Copy <code>sl-inventory</code> into <code>htdocs</code> (XAMPP) or <code>public_html</code> (cPanel). No composer, no build step.
        </p>
      </div>

      <div class="step-i rv">
        <div class="bullet">2</div>
        <h3>Import the database</h3>
        <p style="color:var(--mut);font-size:14.5px;margin:0">
          <code>database.sql</code> creates <code>sl_inventory</code>, all eleven tables, the <code>v_item_stock</code> view
          and <b>one</b> row — the default administrator. Then <code>sl_live_data.sql</code> loads your real masters,
          opening stock and October figures.
        </p>
        <div class="code">
          <button class="copy">Copy</button>
<pre><span class="c"># phpMyAdmin → Import, or from the shell:</span>
mysql -u root -p &lt; <span class="s">database.sql</span>
mysql -u root -p &lt; <span class="s">sl_live_data.sql</span></pre>
        </div>
      </div>

      <div class="step-i rv">
        <div class="bullet">3</div>
        <h3>Edit the top of <code>db.php</code></h3>
        <p style="color:var(--mut);font-size:14.5px;margin:0">
          <code>APP_ADDRESS</code> / <code>APP_GST</code> print on the purchase order.
          <code>SESSION_IDLE_MINUTES</code> (240), <code>LOGIN_MAX_ATTEMPTS</code> (5) and
          <code>LOGIN_LOCK_MINUTES</code> (5) control the session and the brute-force brake.
        </p>
        <div class="code">
          <button class="copy">Copy</button>
<pre><span class="k">define</span>(<span class="s">'DB_HOST'</span>, <span class="s">'localhost'</span>);
<span class="k">define</span>(<span class="s">'DB_NAME'</span>, <span class="s">'sl_inventory'</span>);
<span class="k">define</span>(<span class="s">'DB_USER'</span>, <span class="s">'root'</span>);      <span class="c">// cPanel: your DB user</span>
<span class="k">define</span>(<span class="s">'DB_PASS'</span>, <span class="s">''</span>);          <span class="c">// cPanel: your DB password</span></pre>
        </div>
      </div>

      <div class="step-i rv">
        <div class="bullet">4</div>
        <h3>Log in and change the password</h3>
        <p style="color:var(--mut);font-size:14.5px;margin:0">
          Open <code>login.php</code> — username <code>admin</code>, password <code>admin123</code>.
        </p>
        <div class="note">
          <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
          <p><b>Change this immediately.</b> Log in, open <b>My Account</b>, set a new password. Passwords are stored as bcrypt hashes — never in plain text.</p>
        </div>
      </div>

      <div class="step-i rv">
        <div class="bullet">5</div>
        <h3>First-run order</h3>
        <p style="color:var(--mut);font-size:14.5px;margin:0">
          Login → Suppliers → Item Master → Create PO (saved as Draft) → when the goods arrive,
          <b>Receive (GRN)</b> → Finalize → Stock Out. The dashboard shows a Getting Started checklist
          until the masters have data, and lists every PO still waiting to be received.
        </p>
      </div>
    </div>

    <div class="note rv" style="border-color:rgba(91,147,255,.3);background:linear-gradient(135deg,rgba(91,147,255,.08),rgba(91,147,255,.02))">
      <svg viewBox="0 0 24 24" style="stroke:var(--bl)"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/></svg>
      <p><b style="color:var(--bl)">Upgrading an older install?</b> Run <code>upgrade_v3.sql</code>, <code>upgrade_v4.sql</code>,
      <code>upgrade_v5.sql</code> and <code>upgrade_v6.sql</code> in that order instead of re-importing — together they add the
      receipt tables, the department / equipment layer, the budget table, the issue voucher columns and the direct GRN flag
      while keeping your data. A fresh install needs none of them.</p>
    </div>
  </div>
</section>

<!-- ================= WORKFLOW ================= -->
<section id="workflow">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 03</span>
      <h2>Order first, receive second</h2>
      <p>A purchase order can <b>never</b> be finalized straight from the order screen. It is always saved as a Draft,
         and stock is created only after the delivery is physically checked in.</p>
    </div>

    <div class="flow">
      <div class="step rv" data-d="1">
        <span class="num">01</span>
        <span class="badge draft">Draft</span>
        <h3>Order</h3>
        <p style="color:var(--dim);font-family:var(--mono);font-size:12.5px">create_po.php</p>
        <ul>
          <li>Item, quantity, expected rate</li>
          <li>Rate auto-fills from the last received rate</li>
          <li><code>item_batches</code> untouched — nothing in the valuation</li>
          <li>Editable and deletable · no lot numbers asked for</li>
        </ul>
      </div>

      <div class="conn"><div class="line"></div></div>

      <div class="step rv" data-d="3">
        <span class="num">02</span>
        <span class="badge recv">Receive · GRN</span>
        <h3>What actually arrived</h3>
        <p style="color:var(--dim);font-family:var(--mono);font-size:12.5px">receive_po.php</p>
        <ul>
          <li>Correct the quantity and the rate if the supplier hiked it</li>
          <li>Enter lot numbers and expiry dates</li>
          <li>Split one ordered line across several lots</li>
          <li>Can be saved without finalizing — part delivery</li>
        </ul>
      </div>

      <div class="conn"><div class="line"></div></div>

      <div class="step rv" data-d="5">
        <span class="num">03</span>
        <span class="badge final">Final</span>
        <h3>Finalize</h3>
        <p style="color:var(--dim);font-family:var(--mono);font-size:12.5px">finalize_po()</p>
        <ul>
          <li>Confirmed receipt rows post into <code>item_batches</code></li>
          <li>Transaction-wrapped — a double-click can never post twice</li>
          <li>PO becomes read-only, now headed <b>Goods Receive Note</b></li>
          <li>Finalizing with no receipt rows is refused</li>
        </ul>
      </div>
    </div>

    <div class="grid g2" style="margin-top:22px">
      <div class="card rv" data-d="1">
        <h3>Ordered and received never overwrite each other</h3>
        <p style="margin-bottom:6px">A PO ordered at ₹1,000 and delivered 8 of 10 at a hiked rate of ₹110 keeps the ₹1,000 order
           on record and posts ₹880 of stock. Stock is always valued at the rate actually received.</p>
        <div class="bars" id="bars">
          <div class="bar-row">
            <div class="bar-top"><span>Ordered — <code>po_items</code> · <code>grand_total</code></span><b>₹1,000</b></div>
            <div class="track"><div class="fill order" data-w="100"></div></div>
          </div>
          <div class="bar-row">
            <div class="bar-top"><span>Received — <code>po_receipts</code> · <code>received_total</code></span><b>₹880</b></div>
            <div class="track"><div class="fill recv" data-w="88"></div></div>
          </div>
        </div>
        <p style="margin-top:12px;font-size:13.5px">The difference shows on the PO register, the PO view and the printed sheet.</p>
      </div>

      <div class="card rv" data-d="3">
        <h3>Splitting a line across lots</h3>
        <p>Each ordered line on the Receive screen has an <b>Add another lot for this item</b> button.
           Ten kits delivered as 5 of <code>AS123</code> and 5 of <code>AR123</code> become two receipt rows and two stock lots,
           each with its own expiry and rate.</p>
        <div class="lots">
          <div class="lot"><div class="ln">AS123</div><div class="lq">5 nos · exp 06/2027</div></div>
          <div class="lot"><div class="ln">AR123</div><div class="lq">5 nos · exp 09/2027</div></div>
        </div>
        <p style="margin-top:14px;font-size:13.5px">A received line merges into an existing lot only when item + supplier + lot +
           expiry + rate all match; any difference opens a new lot row. Each receipt row keeps <code>batch_id</code>,
           so every stock lot traces back to the exact PO line it came from.</p>
      </div>
    </div>

    <div class="card rv" style="margin-top:16px">
      <h3>Goods that arrive without an order — <code>direct_grn.php</code></h3>
      <p>An urgent supply, a free replacement, a sample, something bought over the counter. One screen: GRN number (its own
         <code>GRN/…</code> series), supplier, date, invoice number, then the items with their lot, expiry, quantity and rate.
         Saving posts the stock immediately — no draft, no separate receive step.</p>
      <p style="margin-top:10px">Behind the scenes it is stored exactly like a received purchase order
         (<code>purchase_orders.is_direct = 1</code>) with the ordered lines mirroring what was received, so it shows no variance —
         and the stock report, the valuation, the last-rate lookup and the monthly budget all count it the same as any other receipt.</p>
    </div>

    <!-- document series -->
    <div class="sec-head rv" style="margin-top:64px">
      <span class="kicker">Three documents, three number series</span>
      <h2>All defined at the top of <code style="font-size:.7em">db.php</code></h2>
      <p>Renaming any of them is a one-line change that every page follows.</p>
    </div>
    <div class="tbl-wrap rv">
      <table>
        <thead><tr><th>Document</th><th>Number</th><th>What it is</th></tr></thead>
        <tbody>
          <tr><td><b>Purchase Order</b> <code>DOC_PREFIX</code></td><td><code>PO/2026-27/0001</code></td><td>What we ordered. Always a Draft until the goods arrive.</td></tr>
          <tr><td><b>Goods Receive Note</b> <code>GRN_PREFIX</code></td><td><code>GRN/2026-27/0001</code></td><td>Goods received <b>without</b> a purchase order — <code>direct_grn.php</code>.</td></tr>
          <tr><td><b>Issue Voucher</b> <code>ISSUE_PREFIX</code></td><td><code>ISS/2026-27/0001</code></td><td>Stock issued to a department.</td></tr>
        </tbody>
      </table>
    </div>
    <p class="rv" style="color:var(--mut);font-size:14px;margin-top:14px">
      Receiving against a purchase order keeps that order's own <code>PO/…</code> number — the received copy of the order
      <i>is</i> its goods receive note, which is why the printed sheet is headed “Purchase Order” while it is a Draft
      and “Goods Receive Note” once it has been received.
    </p>
  </div>
</section>

<!-- ================= STOCK LOGIC ================= -->
<section id="stock">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 04</span>
      <h2>How the stock logic works</h2>
      <p>Every rupee in the valuation traces back to a lot, and every lot traces back to the receipt that created it.</p>
    </div>

    <div class="grid g2">
      <div class="card rv" data-d="1">
        <h3>Multi-lot items</h3>
        <p><code>items.has_lot = 1</code> means every purchase creates or updates a row in <code>item_batches</code>
           carrying its own <code>lot_number</code>, <code>expiry_date</code>, <code>supplier_id</code> and <code>unit_price</code>.</p>
        <p style="margin-top:10px"><code>has_lot = 0</code> items — gloves, tubes, stationery — are stored under the reserved lot
           <code>GENERAL</code> with a NULL expiry; the PO form hides those two fields automatically.</p>
      </div>
      <div class="card rv" data-d="2">
        <h3>Valuation — no average costing</h3>
        <p>Each lot keeps the rate it was bought at, so the total is exact even when the same item was bought from three
           suppliers at three rates.</p>
        <div class="code">
<pre><span class="f">Current Total Stock Value</span> =
  SUM(item_batches.<span class="s">quantity_available</span>
      * item_batches.<span class="s">unit_price</span>)</pre>
        </div>
      </div>
    </div>

    <div class="grid g2" style="margin-top:16px">
      <div class="card rv" data-d="1">
        <h3>Stock out — FIFO or a lot you pick</h3>
        <p>Each line is taken <b>FIFO</b> — earliest expiry first, lots without an expiry last, spanning as many lots as needed —
           or from one lot you pick from that item's live list. Every slice writes a <code>stock_out</code> row with the lot's own rate,
           so the consumption value is historically correct.</p>
        <div class="lots">
          <div class="lot first"><div class="ln">AS123</div><div class="lq">exp 06/2027 · 5</div></div>
          <span class="arw">→</span>
          <div class="lot"><div class="ln">AR123</div><div class="lq">exp 09/2027 · 5</div></div>
          <span class="arw">→</span>
          <div class="lot"><div class="ln">GENERAL</div><div class="lq">no expiry · 40</div></div>
        </div>
      </div>
      <div class="card rv" data-d="2">
        <h3>One voucher, all or nothing</h3>
        <p>A date, a department, a voucher number and the person who collected the goods, then as many items as needed underneath.
           The number auto-fills as <code>ISS/2026-27/0001</code> but stays editable so it can match your register book.</p>
        <p style="margin-top:10px"><code>issue_voucher()</code> wraps the whole slip in one transaction — if the third item is short
           of stock, the first two are rolled back too and nothing is issued. Short quantities are rejected in full, never partially.</p>
        <p style="margin-top:10px">After saving, the voucher prints with your logo, the lot numbers, the values and signature lines for
           <i>Issued By</i> and <i>Received By</i>. In the register each voucher is shown as a block, and can be reversed one line at a
           time or whole — the quantity goes back to the same lot.</p>
      </div>
    </div>

    <div class="card rv" style="margin-top:16px">
      <h3>Rate auto-fill</h3>
      <p>Picking an item in the PO form calls <code>ajax/get_last_rate.php</code>, which looks for the last <b>received</b> rate for that
         item <i>and</i> supplier, then the last received rate from any supplier, then the opening-stock rate. The field stays fully
         editable — once you type in it, changing the supplier will not overwrite your value.</p>
    </div>

    <div class="note rv">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <p><b>One door into stock.</b> <code>finalize_po()</code> in <code>includes/functions.php</code> is the <b>only</b> place stock is
      created. It flips the status with <code>UPDATE … WHERE id = ? AND status = 'Draft'</code>, so a double-click or two users
      finalizing at once can never post the same quantity twice.</p>
    </div>
  </div>
</section>

<!-- ================= BUDGET ================= -->
<section id="budget">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 05</span>
      <h2>Your Excel budget, inside the system</h2>
      <p><code>sl_live_data.sql</code> carries the real data read out of <code>Nov2026_budget.xls</code> (Sheet4 — the
         Dept / Equipment / Supplier / Item sheet), so the dashboard opens on the same valuation your sheet closed on.</p>
    </div>

    <div class="chain">
      <div class="node rv" data-d="1"><div class="t">Opening</div><div class="v">→</div></div>
      <span class="arw">→</span>
      <div class="node rv" data-d="2"><div class="t">Purchase</div><div class="v">PO receipts</div></div>
      <span class="arw">→</span>
      <div class="node rv" data-d="3"><div class="t">Issues</div><div class="v">Stock Out</div></div>
      <span class="arw">→</span>
      <div class="node rv" data-d="4"><div class="t">Closing</div><div class="v">O + P − I</div></div>
      <span class="arw">→</span>
      <div class="node rv" data-d="5"><div class="t">Required</div><div class="v">next month</div></div>
    </div>

    <div class="tbl-wrap rv" style="margin-top:26px">
      <table>
        <thead><tr><th>October 2026 — carried over unchanged</th><th class="num">Value</th></tr></thead>
        <tbody>
          <tr><td>Opening</td><td class="num" data-count="620426.03" data-prefix="₹" data-dec="2">0</td></tr>
          <tr><td>Purchased</td><td class="num" data-count="1776956.01" data-prefix="₹" data-dec="2">0</td></tr>
          <tr><td>Issued</td><td class="num" data-count="1608571.50" data-prefix="₹" data-dec="2">0</td></tr>
          <tr><td><b>Closing</b> — 108 opening lots</td><td class="num" style="color:var(--cy)" data-count="788810.53" data-prefix="₹" data-dec="2">0</td></tr>
          <tr><td>Required for November</td><td class="num" data-count="1867934.18" data-prefix="₹" data-dec="2">0</td></tr>
        </tbody>
      </table>
    </div>

    <div class="grid g2" style="margin-top:26px">
      <div class="card rv" data-d="1">
        <h3>What the Budget page does</h3>
        <ul class="mini-list" style="padding-left:18px">
          <li><b>Required qty</b> is editable per item. <i>Fill suggested</i> writes <code>issues × cover − closing</code> into every
              blank row, where <i>cover</i> (default 2 months) is yours to set. The rate is editable too.</li>
          <li><b>Save Requirement</b> stores it against the month.</li>
          <li><b>Close month</b> freezes the figures so that month's closing becomes the next month's opening — the same way one
              sheet tab feeds the next.</li>
          <li><b>Create Draft POs (supplier wise)</b> turns the saved requirement into one Draft purchase order per supplier,
              using each item's default supplier. Items with no supplier set are skipped and listed.</li>
          <li><b>Export</b> gives the same table as CSV, so the monthly file can still be mailed as before.</li>
        </ul>
      </div>
      <div class="card rv" data-d="2">
        <h3>Three things to know about the import</h3>
        <ul class="mini-list" style="padding-left:18px">
          <li><b>An item used by more than one department is one store item.</b> The sheet carries “slides” under Haematology and
              Sample Collection separately; the system holds one item with the quantities added, and the department now comes from
              each issue entry — 244 sheet rows became 220 items.</li>
          <li><b>Lot tracking is on for 146 of the 220 items</b> — everything on an analyser, plus anything reading like a reagent,
              kit, calibrator or control. The other 74 (syringes, films, stationery) are no-lot items.</li>
          <li><b>Opening stock has no lot number or expiry</b> — the sheet does not carry them, so lot-tracked items hold a lot
              called <code>OPENING</code> until you replace it. <code>unit</code> defaults to <code>Nos</code>.</li>
        </ul>
        <div class="code">
          <button class="copy">Copy</button>
<pre><span class="k">UPDATE</span> item_batches
   <span class="k">SET</span> lot_number=<span class="s">'AS123'</span>,
       expiry_date=<span class="s">'2027-06-30'</span>
 <span class="k">WHERE</span> item_id=<span class="f">12</span>
   <span class="k">AND</span> lot_number=<span class="s">'OPENING'</span>;</pre>
        </div>
      </div>
    </div>

    <div class="card rv" style="margin-top:16px">
      <h3>Loaded from the sheet</h3>
      <div class="grid g3" style="margin-top:14px;gap:12px">
        <div><div class="t" style="font-size:11.5px;letter-spacing:1px;text-transform:uppercase;color:var(--dim)">9 Departments</div>
          <p style="font-size:13.5px;margin-top:5px">Biochemistry · Haematology · Microbiology · GCC · Radiology · Ultrasound · Sample Collection · Diagnostics · General</p></div>
        <div><div class="t" style="font-size:11.5px;letter-spacing:1px;text-transform:uppercase;color:var(--dim)">20 Analysers</div>
          <p style="font-size:13.5px;margin-top:5px">AU480 · Beckman Unicel Dxc · Mini Vidas · Coagulometer · D10 · Erba Chem · Meril · Abbott · EM360 · H360 · Cobas Urine · Erba H7100 · Mindray · Mispa i3 · Elisa · Cultures · Manual · Vaccine …</p></div>
        <div><div class="t" style="font-size:11.5px;letter-spacing:1px;text-transform:uppercase;color:var(--dim)">33 Suppliers · 220 Items</div>
          <p style="font-size:13.5px;margin-top:5px">Each item with its department, analyser, default supplier and item code. Departments and analysers are maintained in <code>departments.php</code>.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ================= ROLES ================= -->
<section id="roles">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 06</span>
      <h2>Roles &amp; security</h2>
      <p>A User can use every page except <code>users.php</code>; only an Admin can create logins. The guard is server-side —
         <code>require_login()</code> on every page and <code>require_admin()</code> on <code>users.php</code> — so typing the URL
         directly does not get a User in.</p>
    </div>

    <div class="tbl-wrap rv">
      <table>
        <thead><tr><th>Page</th><th style="text-align:center">Admin</th><th style="text-align:center">User</th></tr></thead>
        <tbody>
          <tr><td>Dashboard, Suppliers, Item Master, Departments</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="yes">✔</td></tr>
          <tr><td>Monthly Budget, requirement, draft POs from it</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="yes">✔</td></tr>
          <tr><td>Create PO, Purchase Orders, Final conversion, Print</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="yes">✔</td></tr>
          <tr><td>Stock &amp; Valuation, CSV export, Stock Out, Reverse</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="yes">✔</td></tr>
          <tr><td>My Account (own name / password)</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="yes">✔</td></tr>
          <tr><td><b>User Management</b> — create logins, set roles</td><td style="text-align:center" class="yes">✔</td><td style="text-align:center" class="no">✖</td></tr>
        </tbody>
      </table>
    </div>

    <div class="grid g2" style="margin-top:20px">
      <div class="card rv" data-d="1">
        <h3>Guards that cannot be overridden from the UI</h3>
        <p>You cannot delete or deactivate your own login, and the <b>last active Admin</b> cannot be deleted, deactivated or
           demoted — so the system can never be locked out. The ajax endpoints answer <code>401</code> to a dead session instead
           of leaking data.</p>
        <p style="margin-top:10px">Every purchase order stores who created it and who finalized it; every stock-out line stores who
           issued it. Those names show on the PO register, the PO view (also under “Prepared By” when printed) and the issue
           register — and survive the deletion of that login.</p>
      </div>
      <div class="card rv" data-d="2">
        <h3>Security built in</h3>
        <div class="sec-chips" style="margin-top:12px">
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>bcrypt password_hash</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>CSRF token on every form</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>Prepared statements</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg><code style="background:none;padding:0;color:inherit">h()</code> on all output</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>HttpOnly + SameSite=Lax</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>Secure cookie over HTTPS</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>Session ID regenerated on login</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>Idle timeout · 240 min</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>5-attempt lockout · 5 min</span>
          <span class="sc"><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>Post-login redirect whitelist</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= FILES ================= -->
<section id="files">
  <div class="container">
    <div class="sec-head rv">
      <span class="kicker">Section 07</span>
      <h2>Folder structure</h2>
      <p>One flat application folder. Every page sets <code>$page_title</code> and <code>$active</code> and includes the shared header.</p>
    </div>

    <div class="tbl-wrap rv" style="background:rgba(5,8,16,.6)">
<pre class="tree"><span class="d">sl-inventory/</span>
├── <span class="hot">database.sql</span>              <span class="c">Clean MySQL schema + ONE default admin login</span>
├── <span class="hot">sl_live_data.sql</span>          <span class="c">Your data: 9 depts, 20 analysers, 33 suppliers,</span>
│                             <span class="c">220 items, opening stock, Oct-2026 budget figures</span>
├── <span class="f">upgrade_v3.sql</span>            <span class="c">ONLY for an older install with data to keep</span>
├── <span class="f">upgrade_v4.sql</span>            <span class="c">ONLY for an older install: dept / equipment / budget</span>
├── <span class="f">upgrade_v5.sql</span>            <span class="c">ONLY for an older install: issue voucher columns</span>
├── <span class="f">upgrade_v6.sql</span>            <span class="c">ONLY for an older install: direct GRN flag</span>
├── <span class="hot">logo.png</span>                  <span class="c">← PUT YOUR LOGO HERE</span>
├── <span class="hot">db.php</span>                    <span class="c">App constants, session settings, PDO (EDIT THIS)</span>
├── <span class="f">login.php</span>                 <span class="c">Sign in (split-panel on desktop, single column on mobile)</span>
├── <span class="f">logout.php</span>                <span class="c">Sign out</span>
├── <span class="f">index.php</span>                 <span class="c">Dashboard — stock value, low stock, expiry, POs to receive</span>
├── <span class="f">suppliers.php</span>             <span class="c">Supplier master (CRUD)</span>
├── <span class="f">items.php</span>                 <span class="c">Item master (CRUD): lot switch, dept, analyser, supplier</span>
├── <span class="f">departments.php</span>           <span class="c">Departments + the equipment / analysers inside them</span>
├── <span class="f">budget.php</span>                <span class="c">Monthly budget — the Excel sheet, inside the system</span>
├── <span class="f">create_po.php</span>             <span class="c">Raise / edit a Draft PO (AJAX rate auto-fill)</span>
├── <span class="f">receive_po.php</span>            <span class="c">Receive (GRN) — qty, rate, lot numbers, then finalize</span>
├── <span class="f">direct_grn.php</span>            <span class="c">Direct GRN — goods received with no purchase order</span>
├── <span class="f">po_list.php</span>               <span class="c">PO register — ordered vs received, variance</span>
├── <span class="f">view_po.php</span>               <span class="c">PO view + A4 print with logo + goods-received comparison</span>
├── <span class="f">stock_report.php</span>          <span class="c">Stock &amp; valuation (lot-wise / item-wise) + CSV export</span>
├── <span class="f">stock_out.php</span>             <span class="c">Issue voucher — many items per slip + issue register</span>
├── <span class="f">users.php</span>                 <span class="c">User Management  ← ADMIN ONLY</span>
├── <span class="f">profile.php</span>               <span class="c">My Account — own name + own password (both roles)</span>
├── <span class="d">includes/</span>
│   ├── <span class="f">functions.php</span>         <span class="c">Helpers + ALL stock business logic</span>
│   ├── <span class="f">auth.php</span>              <span class="c">Login session, roles, page guards</span>
│   ├── <span class="f">header.php</span>            <span class="c">&lt;head&gt;, sidebar, topbar  (set $page_title, $active)</span>
│   └── <span class="f">footer.php</span>            <span class="c">Closing markup + scripts</span>
├── <span class="d">ajax/</span>
│   ├── <span class="f">get_last_rate.php</span>     <span class="c">?item_id=&amp;supplier_id= → last purchase rate + item info</span>
│   └── <span class="f">get_batches.php</span>       <span class="c">?item_id= → available lots in FIFO order</span>
└── <span class="d">assets/</span>
    ├── <span class="f">css/style.css</span>         <span class="c">Theme, login page, responsive sidebar, print stylesheet</span>
    └── <span class="f">js/app.js</span>             <span class="c">Shared JS (number formatting, fetch wrapper, confirms)</span></pre>
    </div>

    <!-- logo + notes -->
    <div class="grid g2" style="margin-top:26px">
      <div class="card rv" data-d="1">
        <h3>Your logo</h3>
        <p>Put <code>logo.png</code> in the application root, next to <code>db.php</code>. It is used automatically on the printed
           purchase order header, the login page, the sidebar brand area and the browser tab icon.</p>
        <p style="margin-top:10px">Nothing breaks if the file is missing — every spot falls back to the text wordmark
           “SL Diagnostics” in Times New Roman Bold. Capped at 64px tall on the PO, 66px on login and 46px in the sidebar;
           width scales automatically. The <code>?v=</code> on the URL is the file's own modified time, so a replaced logo appears
           immediately instead of a cached one. To use a different file name, change <code>APP_LOGO</code> in <code>db.php</code>.</p>
      </div>
      <div class="card rv" data-d="2">
        <h3>Notes worth keeping</h3>
        <ul class="mini-list" style="padding-left:18px">
          <li>Bootstrap 5.3 and Bootstrap Icons load from jsDelivr — to go CDN-free, drop the files into <code>assets/</code> and change
              the tags in <code>includes/header.php</code>, <code>includes/footer.php</code> and <code>login.php</code>.</li>
          <li>Opening stock without a PO: either raise one PO per supplier and receive it with the real lot numbers (recommended —
              it keeps the audit trail), or insert the rows straight into <code>item_batches</code>.</li>
          <li>Editing a Draft PO after a receipt has been saved against it clears that saved receipt, since the ordered lines it
              referred to are replaced.</li>
          <li>Deletions are protected by referential checks: a supplier with POs, an item used in a PO or still holding stock, and
              any Final PO cannot be deleted — deactivate instead.</li>
          <li>Beyond the original spec: <code>is_active</code> on suppliers/items/users, <code>created_by</code>,
              <code>finalized_by</code>, <code>finalized_at</code>, <code>received_total</code>, <code>receive_date</code>,
              <code>invoice_no</code>, <code>receive_notes</code>, <code>po_id</code> on <code>item_batches</code>,
              <code>issued_by</code> on <code>stock_out</code>, plus the <code>users</code>, <code>po_receipts</code> and
              <code>stock_out</code> tables and the <code>v_item_stock</code> view.</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ================= CREATOR ================= -->
<section id="creator">
  <div class="container">
    <div class="creator rv">
      <div style="text-align:center;position:relative;z-index:1">
        <div class="avatar">SJ</div>
        <span class="kicker" style="margin-bottom:6px">Designed &amp; developed by</span>
        <h2 style="margin:4px 0 0">SmartJOANS</h2>
        <p style="color:var(--mut);max-width:620px;margin:14px auto 0;font-size:15px">
          Full-stack web development — PHP / MySQL systems, ASP.NET, Node.js services and digital tools.
          This inventory system is one of a family of products built for laboratories and for the web.
        </p>

        <div class="sites">
          <a class="site" href="https://www.smartjoans.space" target="_blank" rel="noopener">
            <span class="si"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/></svg></span>
            <span><span class="st">SmartJOANS</span><br><span class="sd">www.smartjoans.space</span></span>
            <span class="go">↗</span>
          </a>
          <a class="site" href="https://books.smartjoans.space" target="_blank" rel="noopener">
            <span class="si"><svg viewBox="0 0 24 24"><path d="M4 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4z"/><path d="M20 4h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg></span>
            <span><span class="st">SmartLib</span><br><span class="sd">books.smartjoans.space</span></span>
            <span class="go">↗</span>
          </a>
          <a class="site" href="https://sharable.smartjoans.space" target="_blank" rel="noopener">
            <span class="si"><svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/></svg></span>
            <span><span class="st">Sharable</span><br><span class="sd">sharable.smartjoans.space</span></span>
            <span class="go">↗</span>
          </a>
          <a class="site" href="https://myprofile.cc" target="_blank" rel="noopener">
            <span class="si"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2.2"/><path d="M5.5 17c.8-2 2-3 3.5-3s2.7 1 3.5 3"/><path d="M15 9h4"/><path d="M15 13h4"/></svg></span>
            <span><span class="st">myprofile.cc</span><br><span class="sd">digital CV platform</span></span>
            <span class="go">↗</span>
          </a>
        </div>

        <a class="ig" href="https://www.instagram.com/smart_joans" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg>
          @smart_joans
        </a>

        <p style="color:var(--dim);font-size:12.8px;margin-top:24px">
          Also built: <a href="https://artintentions.in" target="_blank" rel="noopener" style="color:var(--mut);text-decoration:none;border-bottom:1px dotted var(--dim)">artintentions.in</a>
          · <a href="https://writerpraba.co.in" target="_blank" rel="noopener" style="color:var(--mut);text-decoration:none;border-bottom:1px dotted var(--dim)">writerpraba.co.in</a>
        </p>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <p style="margin:0 0 6px">
      <b style="font-family:'Times New Roman',Times,serif;font-size:16px;color:var(--txt)">SL Diagnostics</b> — Laboratory Inventory &amp; Stock Management System
    </p>
    <p style="margin:0">
      Built by <a href="https://www.smartjoans.space" target="_blank" rel="noopener">SmartJOANS</a>
      · <a href="https://www.instagram.com/smart_joans" target="_blank" rel="noopener">@smart_joans</a>
      · Core PHP · MySQL · Bootstrap 5
    </p>
  </div>
</footer>

</div>

<button class="top" id="toTop" aria-label="Back to top">
  <svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
</button>

<script>
(function(){
  "use strict";
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- scroll reveal ---------- */
  var revs = document.querySelectorAll('.rv');
  if('IntersectionObserver' in window && !reduce){
    var io = new IntersectionObserver(function(es){
      es.forEach(function(e){
        if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); }
      });
    },{threshold:.12,rootMargin:'0px 0px -40px 0px'});
    revs.forEach(function(el){ io.observe(el); });
  } else {
    revs.forEach(function(el){ el.classList.add('in'); });
  }

  /* ---------- indian number format ---------- */
  function inr(n, dec){
    var s = Math.abs(n).toFixed(dec);
    var p = s.split('.');
    var i = p[0], last3 = i.slice(-3), rest = i.slice(0,-3);
    if(rest) last3 = ',' + last3;
    rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
    return rest + last3 + (p[1] ? '.' + p[1] : '');
  }

  /* ---------- count up ---------- */
  function countUp(el){
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dec = parseInt(el.getAttribute('data-dec') || '0', 10);
    var pre = el.getAttribute('data-prefix') || '';
    if(reduce){ el.textContent = pre + inr(target, dec); return; }
    var dur = 1500, t0 = null;
    function tick(ts){
      if(!t0) t0 = ts;
      var p = Math.min((ts - t0)/dur, 1);
      var e = 1 - Math.pow(1 - p, 3);
      el.textContent = pre + inr(target * e, dec);
      if(p < 1) requestAnimationFrame(tick);
      else el.textContent = pre + inr(target, dec);
    }
    requestAnimationFrame(tick);
  }
  var nums = document.querySelectorAll('[data-count]');
  if('IntersectionObserver' in window){
    var io2 = new IntersectionObserver(function(es){
      es.forEach(function(e){ if(e.isIntersecting){ countUp(e.target); io2.unobserve(e.target); } });
    },{threshold:.4});
    nums.forEach(function(n){ io2.observe(n); });
  } else { nums.forEach(countUp); }

  /* ---------- variance bars ---------- */
  var bars = document.getElementById('bars');
  if(bars && 'IntersectionObserver' in window){
    var io3 = new IntersectionObserver(function(es){
      es.forEach(function(e){
        if(e.isIntersecting){
          e.target.querySelectorAll('.fill').forEach(function(f,i){
            setTimeout(function(){ f.style.width = f.getAttribute('data-w') + '%'; }, 120 + i*260);
          });
          io3.unobserve(e.target);
        }
      });
    },{threshold:.35});
    io3.observe(bars);
  } else if(bars){
    bars.querySelectorAll('.fill').forEach(function(f){ f.style.width = f.getAttribute('data-w') + '%'; });
  }

  /* ---------- install stepper line ---------- */
  var steps = document.getElementById('steps'), fl = document.getElementById('fillline');
  var stepItems = steps ? steps.querySelectorAll('.step-i') : [];
  function paintSteps(){
    if(!steps || !fl) return;
    var mid = window.innerHeight * 0.62, done = 0;
    stepItems.forEach(function(s){
      var r = s.getBoundingClientRect();
      if(r.top < mid){ s.classList.add('in'); done = Math.max(done, r.top + r.height/2); }
    });
    var sr = steps.getBoundingClientRect();
    var h = Math.max(0, Math.min(mid - sr.top, sr.height - 16));
    fl.style.height = h + 'px';
  }

  /* ---------- nav progress + active link + to-top ---------- */
  var prog = document.getElementById('prog');
  var toTop = document.getElementById('toTop');
  var links = Array.prototype.slice.call(document.querySelectorAll('.nav-links a'));
  var secs = links.map(function(a){ return document.querySelector(a.getAttribute('href')); });
  var ticking = false;
  function onScroll(){
    var y = window.pageYOffset || document.documentElement.scrollTop;
    var h = document.documentElement.scrollHeight - window.innerHeight;
    if(prog) prog.style.width = (h > 0 ? (y/h)*100 : 0) + '%';
    if(toTop) toTop.classList.toggle('show', y > 700);
    var cur = -1;
    secs.forEach(function(s,i){ if(s && s.getBoundingClientRect().top <= 140) cur = i; });
    links.forEach(function(a,i){ a.classList.toggle('on', i === cur); });
    paintSteps();
    ticking = false;
  }
  window.addEventListener('scroll', function(){
    if(!ticking){ ticking = true; requestAnimationFrame(onScroll); }
  }, {passive:true});
  window.addEventListener('resize', onScroll);
  onScroll();

  if(toTop) toTop.addEventListener('click', function(){
    window.scrollTo({top:0, behavior: reduce ? 'auto' : 'smooth'});
  });

  /* ---------- copy buttons ---------- */
  document.querySelectorAll('.copy').forEach(function(btn){
    btn.addEventListener('click', function(){
      var pre = btn.parentNode.querySelector('pre');
      if(!pre) return;
      var txt = pre.innerText;
      var done = function(){
        btn.textContent = 'Copied'; btn.classList.add('done');
        setTimeout(function(){ btn.textContent = 'Copy'; btn.classList.remove('done'); }, 1600);
      };
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(txt).then(done).catch(function(){ fallback(txt, done); });
      } else { fallback(txt, done); }
    });
  });
  function fallback(txt, cb){
    var ta = document.createElement('textarea');
    ta.value = txt; ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); cb(); }catch(e){}
    document.body.removeChild(ta);
  }

  /* ---------- hero terminal typing ---------- */
  var term = document.getElementById('term');
  if(term){
    var lines = [
      { el: term.querySelector('.t1'), txt: 'mysql -u root -p < database.sql', caret:true },
      { el: term.querySelector('.t2'), txt: 'mysql -u root -p < sl_live_data.sql', caret:true },
      { el: term.querySelector('.t3'), txt: '✓ 11 tables · 220 items · 108 lots · ₹7,88,810.53 opening stock', caret:false }
    ];
    if(reduce){
      lines.forEach(function(l){ l.el.textContent = l.txt; });
    } else {
      var li = 0;
      function typeLine(){
        if(li >= lines.length) return;
        var l = lines[li], i = 0;
        var caret = document.createElement('span');
        caret.className = 'caret';
        l.el.parentNode.appendChild(caret);
        var speed = li === 2 ? 14 : 36;
        var iv = setInterval(function(){
          l.el.textContent = l.txt.slice(0, ++i);
          if(i >= l.txt.length){
            clearInterval(iv);
            if(caret.parentNode) caret.parentNode.removeChild(caret);
            li++;
            setTimeout(typeLine, 320);
          }
        }, speed);
      }
      setTimeout(typeLine, 700);
    }
  }
})();
</script>
</body>
</html>
