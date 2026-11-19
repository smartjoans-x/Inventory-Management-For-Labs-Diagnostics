<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SL Diagnostics — Laboratory Inventory &amp; Stock Management System</title>
<meta name="description" content="Documentation for the SL Diagnostics laboratory inventory and stock management system: Core PHP + MySQL, two-stage Order → Receive (GRN) workflow, lot-wise valuation, FIFO issue vouchers and a live monthly budget. Built by SmartJOANS.">
<meta name="theme-color" content="#0f766e">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230f766e'/%3E%3Ctext x='32' y='44' font-size='30' font-family='Times New Roman' font-weight='bold' fill='white' text-anchor='middle'%3ESL%3C/text%3E%3C/svg%3E">
<style>
/* ══════════════════════════════════════════════════════════════
   SL Diagnostics — Inventory & Stock Management · Documentation
   Single file · no CDN · no build step
   ══════════════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box}

:root{
  --teal:#0f766e; --teal-2:#14b8a6; --blue:#2563eb; --violet:#7c3aed;
  --amber:#d97706; --rose:#e11d48;
  --bg:#f6f8fb; --surface:#ffffff; --surface-2:#f2f5f9; --surface-3:#eaeff6;
  --ink:#0c1a2b; --ink-2:#2a3b52; --mut:#5b6b85; --dim:#8695ab;
  --line:#e2e8f1; --line-2:#d5dde9;
  --code-bg:#0f172a; --code-ink:#e2e8f0;
  --shadow-s:0 1px 2px rgba(12,26,43,.05),0 1px 3px rgba(12,26,43,.06);
  --shadow-m:0 10px 30px -14px rgba(12,26,43,.28),0 2px 8px rgba(12,26,43,.05);
  --shadow-l:0 30px 70px -32px rgba(12,26,43,.42);
  --grad:linear-gradient(120deg,#0f766e,#0ea5a5 42%,#2563eb);
  --grad-soft:linear-gradient(120deg,rgba(15,118,110,.10),rgba(37,99,235,.10));
  --r:14px; --r-lg:22px;
  --mono:ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,"Liberation Mono",monospace;
  --sans:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  --sb:272px;
}
[data-theme="dark"]{
  --bg:#080c14; --surface:#0e1420; --surface-2:#131b2a; --surface-3:#192234;
  --ink:#eaf1fb; --ink-2:#cdd9ea; --mut:#93a3bd; --dim:#6b7c97;
  --line:#1e2839; --line-2:#28344a;
  --code-bg:#070b13; --code-ink:#dbe6f5;
  --teal:#2dd4bf; --teal-2:#5eead4; --blue:#60a5fa; --violet:#a78bfa;
  --amber:#fbbf24; --rose:#fb7185;
  --shadow-s:0 1px 2px rgba(0,0,0,.4);
  --shadow-m:0 14px 36px -18px rgba(0,0,0,.8);
  --shadow-l:0 36px 80px -40px rgba(0,0,0,.95);
  --grad:linear-gradient(120deg,#2dd4bf,#38bdf8 45%,#a78bfa);
  --grad-soft:linear-gradient(120deg,rgba(45,212,191,.12),rgba(167,139,250,.12));
}
html{scroll-behavior:smooth;scroll-padding-top:86px;-webkit-text-size-adjust:100%}
body{
  margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:16px;line-height:1.7;
  -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;overflow-x:hidden;
  transition:background .4s ease,color .4s ease;
}
a{color:var(--teal);text-decoration:none}
::selection{background:rgba(20,184,166,.25)}
h1,h2,h3,h4{line-height:1.22;letter-spacing:-.02em;margin:0}
p{margin:0}

/* ─── topbar ─────────────────────────────────────────────── */
.topbar{
  position:sticky;top:0;z-index:60;height:66px;display:flex;align-items:center;gap:14px;
  padding:0 20px;border-bottom:1px solid var(--line);
  background:color-mix(in srgb,var(--surface) 86%,transparent);
  backdrop-filter:blur(14px) saturate(160%);-webkit-backdrop-filter:blur(14px) saturate(160%);
}
@supports not (background:color-mix(in srgb,red 50%,blue)){.topbar{background:var(--surface)}}
.brand{display:flex;align-items:center;gap:11px;color:inherit}
.brand .mark{
  width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:0 0 auto;
  background:var(--grad);color:#fff;font-family:"Times New Roman",Times,serif;font-weight:bold;font-size:16px;
  box-shadow:0 8px 20px -8px rgba(15,118,110,.8)
}
.brand .txt b{display:block;font-family:"Times New Roman",Times,serif;font-size:17px;letter-spacing:.2px;line-height:1.1}
.brand .txt span{display:block;font-size:11px;color:var(--dim);letter-spacing:.9px;text-transform:uppercase}
.tb-right{margin-left:auto;display:flex;align-items:center;gap:9px}
.iconbtn{
  width:38px;height:38px;border-radius:11px;border:1px solid var(--line);background:var(--surface);
  display:grid;place-items:center;cursor:pointer;color:var(--mut);transition:.25s
}
.iconbtn:hover{color:var(--ink);border-color:var(--line-2);transform:translateY(-1px)}
.iconbtn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.sun{display:none}[data-theme="dark"] .sun{display:block}[data-theme="dark"] .moon{display:none}
.tb-cta{
  display:inline-flex;align-items:center;gap:8px;padding:10px 17px;border-radius:11px;font-size:14px;font-weight:650;
  background:var(--grad);color:#fff;box-shadow:0 10px 24px -12px rgba(15,118,110,.9);transition:.25s
}
.tb-cta:hover{transform:translateY(-2px);box-shadow:0 16px 30px -12px rgba(15,118,110,1)}
.tb-cta svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
#menuBtn{display:none}
.scrollbar{position:absolute;left:0;bottom:-1px;height:2px;width:0;background:var(--grad)}
@media(max-width:1040px){#menuBtn{display:grid}.tb-cta span{display:none}.tb-cta{padding:10px 12px}}
@media(max-width:520px){.brand .txt span{display:none}}

/* ─── shell ──────────────────────────────────────────────── */
.shell{display:grid;grid-template-columns:var(--sb) minmax(0,1fr);gap:0;align-items:start}
aside{
  position:sticky;top:66px;height:calc(100vh - 66px);overflow-y:auto;padding:26px 14px 60px;
  border-right:1px solid var(--line);background:var(--surface);scrollbar-width:thin
}
aside::-webkit-scrollbar{width:8px}
aside::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:8px}
.nav-group{margin-bottom:22px}
.nav-group h5{
  font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:var(--dim);margin:0 0 8px;padding-left:13px;font-weight:700
}
.nav-group a{
  display:flex;align-items:center;gap:10px;padding:8px 13px;border-radius:10px;color:var(--mut);font-size:14px;
  position:relative;transition:.2s;font-weight:500
}
.nav-group a:hover{background:var(--surface-2);color:var(--ink)}
.nav-group a.on{color:var(--teal);background:var(--grad-soft);font-weight:650}
.nav-group a.on::before{
  content:"";position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:17px;border-radius:0 3px 3px 0;background:var(--teal)
}
.nav-group a .ni{width:16px;height:16px;flex:0 0 auto;opacity:.75}
.nav-group a .ni svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-foot{margin-top:28px;padding:15px 13px;border-radius:var(--r);background:var(--surface-2);border:1px solid var(--line)}
.sb-foot p{font-size:12.5px;color:var(--mut);margin-bottom:9px}
.sb-foot a.mini{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:650}
.backdrop{display:none}
@media(max-width:1040px){
  .shell{grid-template-columns:1fr}
  aside{
    position:fixed;left:0;top:66px;width:300px;z-index:70;transform:translateX(-104%);
    transition:transform .35s cubic-bezier(.22,1,.36,1);box-shadow:var(--shadow-l)
  }
  aside.open{transform:none}
  .backdrop{display:block;position:fixed;inset:66px 0 0;background:rgba(5,10,18,.5);opacity:0;pointer-events:none;transition:.3s;z-index:65}
  .backdrop.on{opacity:1;pointer-events:auto}
}

/* ─── main ───────────────────────────────────────────────── */
main{padding:0 clamp(18px,4vw,56px) 90px;max-width:1000px;width:100%}
section{padding-top:72px;scroll-margin-top:84px}
.sec-label{
  display:inline-flex;align-items:center;gap:7px;font-size:11px;letter-spacing:1.6px;text-transform:uppercase;
  font-weight:800;color:var(--teal);margin-bottom:12px
}
.sec-label::before{content:"";width:16px;height:2px;border-radius:2px;background:var(--teal)}
h2{font-size:clamp(1.55rem,3.3vw,2.1rem);margin-bottom:12px}
h3{font-size:1.07rem;margin-bottom:7px;font-weight:700}
.sub{color:var(--mut);font-size:16px;max-width:72ch}
.sec-head{margin-bottom:28px}

/* ─── hero ───────────────────────────────────────────────── */
.hero{
  position:relative;overflow:hidden;border-radius:var(--r-lg);border:1px solid var(--line);
  background:var(--surface);box-shadow:var(--shadow-m);padding:clamp(28px,5vw,54px);margin-top:36px
}
.hero::before{
  content:"";position:absolute;inset:0;background:var(--grad-soft);opacity:.9
}
.hero::after{
  content:"";position:absolute;width:520px;height:520px;right:-190px;top:-230px;border-radius:50%;
  background:radial-gradient(circle,rgba(20,184,166,.28),transparent 68%);animation:drift 18s ease-in-out infinite
}
@keyframes drift{0%,100%{transform:translate(0,0)}50%{transform:translate(-44px,42px) scale(1.1)}}
.hero > *{position:relative;z-index:1}
.tag{
  display:inline-flex;align-items:center;gap:9px;padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;
  background:var(--surface);border:1px solid var(--line);color:var(--mut);box-shadow:var(--shadow-s)
}
.live{width:7px;height:7px;border-radius:50%;background:var(--teal-2);box-shadow:0 0 0 0 rgba(20,184,166,.6);animation:ping 2.4s infinite}
@keyframes ping{70%{box-shadow:0 0 0 10px rgba(20,184,166,0)}100%{box-shadow:0 0 0 0 rgba(20,184,166,0)}}
.hero h1{font-size:clamp(2rem,5.4vw,3.35rem);margin:20px 0 0;letter-spacing:-.035em;font-weight:820}
.hero h1 em{
  font-style:normal;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent
}
.hero .lede{margin-top:17px;font-size:clamp(1rem,2.1vw,1.12rem);color:var(--mut);max-width:62ch}
.hero-cta{display:flex;flex-wrap:wrap;gap:11px;margin-top:28px}
.btn{
  display:inline-flex;align-items:center;gap:9px;padding:12px 21px;border-radius:12px;font-size:14.5px;font-weight:650;
  border:1px solid transparent;cursor:pointer;transition:.25s;font-family:inherit
}
.btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn.p{background:var(--grad);color:#fff;box-shadow:0 12px 26px -13px rgba(15,118,110,.95)}
.btn.p:hover{transform:translateY(-2px);box-shadow:0 18px 34px -13px rgba(15,118,110,1)}
.btn.g{background:var(--surface);color:var(--ink);border-color:var(--line-2);box-shadow:var(--shadow-s)}
.btn.g:hover{transform:translateY(-2px);border-color:var(--teal);color:var(--teal)}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:26px}
.meta span{
  font-size:12.3px;padding:5px 12px;border-radius:999px;background:var(--surface);border:1px solid var(--line);
  color:var(--mut);font-weight:550
}

/* ─── stats ──────────────────────────────────────────────── */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:22px}
.stat{
  border:1px solid var(--line);background:var(--surface);border-radius:var(--r);padding:18px;box-shadow:var(--shadow-s);
  transition:.3s
}
.stat:hover{transform:translateY(-3px);box-shadow:var(--shadow-m);border-color:var(--teal)}
.stat b{display:block;font-size:1.75rem;font-weight:800;letter-spacing:-.04em;
  background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat small{display:block;font-size:11.5px;color:var(--dim);text-transform:uppercase;letter-spacing:.9px;margin-top:2px;font-weight:650}

/* ─── reveal ─────────────────────────────────────────────── */
.rv{opacity:0;transform:translateY(22px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.rv.in{opacity:1;transform:none}
.rv[data-d="1"]{transition-delay:.06s}.rv[data-d="2"]{transition-delay:.12s}
.rv[data-d="3"]{transition-delay:.18s}.rv[data-d="4"]{transition-delay:.24s}
.rv[data-d="5"]{transition-delay:.3s}.rv[data-d="6"]{transition-delay:.36s}

/* ─── cards ──────────────────────────────────────────────── */
.grid{display:grid;gap:14px}
.g2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.g3{grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
.card{
  border:1px solid var(--line);background:var(--surface);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-s);
  transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s,border-color .35s;position:relative;overflow:hidden
}
.card:hover{transform:translateY(-4px);box-shadow:var(--shadow-m);border-color:var(--line-2)}
.card > p{color:var(--mut);font-size:14.6px}
.card p + p{margin-top:10px}
.ico{
  width:40px;height:40px;border-radius:11px;display:grid;place-items:center;margin-bottom:14px;
  background:var(--grad-soft);border:1px solid var(--line)
}
.ico svg{width:19px;height:19px;stroke:var(--teal);fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
ul.tidy{margin:10px 0 0;padding-left:19px;color:var(--mut);font-size:14.3px}
ul.tidy li{margin:7px 0}
ul.tidy li::marker{color:var(--teal)}

/* ─── code ───────────────────────────────────────────────── */
.codebox{
  margin-top:14px;border-radius:var(--r);overflow:hidden;border:1px solid var(--line);background:var(--code-bg);
  box-shadow:var(--shadow-s)
}
.codebar{
  display:flex;align-items:center;gap:9px;padding:9px 14px;background:rgba(255,255,255,.04);
  border-bottom:1px solid rgba(255,255,255,.07)
}
.codebar .nm{font-family:var(--mono);font-size:11.5px;color:#94a3b8;letter-spacing:.3px}
.codebar .cp{
  margin-left:auto;font-size:11px;font-weight:650;color:#94a3b8;background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:4px 10px;cursor:pointer;transition:.2s;font-family:inherit
}
.codebar .cp:hover{color:#fff;background:rgba(255,255,255,.14)}
.codebar .cp.ok{color:#5eead4;border-color:rgba(94,234,212,.4)}
.codebox pre{
  margin:0;padding:16px 18px;font-family:var(--mono);font-size:13px;line-height:1.85;color:var(--code-ink);
  overflow-x:auto;white-space:pre
}
.c-c{color:#64748b;font-style:italic}.c-k{color:#c084fc}.c-s{color:#5eead4}.c-n{color:#fbbf24}.c-fn{color:#60a5fa}
code.inl{
  font-family:var(--mono);font-size:.86em;background:var(--surface-3);color:var(--teal);
  padding:2px 7px;border-radius:6px;border:1px solid var(--line);white-space:nowrap
}

/* ─── tree ───────────────────────────────────────────────── */
.tree{
  border:1px solid var(--line);border-radius:var(--r);background:var(--code-bg);box-shadow:var(--shadow-s);overflow:hidden
}
.tree pre{
  margin:0;padding:20px;font-family:var(--mono);font-size:12.6px;line-height:1.95;color:#cbd5e1;overflow-x:auto;white-space:pre
}
.tree .d{color:#60a5fa;font-weight:600}
.tree .h{color:#5eead4;font-weight:600}
.tree .c{color:#64748b}

/* ─── steps ──────────────────────────────────────────────── */
.steps{position:relative;margin-top:8px}
.steps .rail{position:absolute;left:19px;top:14px;bottom:14px;width:2px;background:var(--line)}
.steps .rail i{position:absolute;inset:0 auto 0 0;width:2px;height:0;background:var(--grad);transition:height .9s ease;display:block}
.stp{position:relative;padding:0 0 26px 58px}
.stp:last-child{padding-bottom:0}
.stp .dot{
  position:absolute;left:0;top:-2px;width:40px;height:40px;border-radius:13px;display:grid;place-items:center;
  background:var(--surface);border:1px solid var(--line);color:var(--dim);font-weight:800;font-size:14px;
  box-shadow:var(--shadow-s);transition:.45s cubic-bezier(.22,1,.36,1);z-index:1
}
.stp.hit .dot{background:var(--grad);color:#fff;border-color:transparent;box-shadow:0 10px 22px -10px rgba(15,118,110,.9);transform:scale(1.06)}
.stp p{color:var(--mut);font-size:14.6px}

/* ─── callouts ───────────────────────────────────────────── */
.callout{
  display:flex;gap:13px;align-items:flex-start;border-radius:var(--r);padding:15px 17px;margin-top:16px;
  border:1px solid;font-size:14.4px
}
.callout svg{width:19px;height:19px;flex:0 0 auto;margin-top:2px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.callout p{color:var(--ink-2)}
.callout.warn{background:color-mix(in srgb,var(--amber) 9%,var(--surface));border-color:color-mix(in srgb,var(--amber) 32%,var(--line))}
.callout.warn svg{stroke:var(--amber)}
.callout.info{background:color-mix(in srgb,var(--blue) 8%,var(--surface));border-color:color-mix(in srgb,var(--blue) 28%,var(--line))}
.callout.info svg{stroke:var(--blue)}
.callout.good{background:color-mix(in srgb,var(--teal) 9%,var(--surface));border-color:color-mix(in srgb,var(--teal) 30%,var(--line))}
.callout.good svg{stroke:var(--teal)}
@supports not (background:color-mix(in srgb,red 50%,blue)){
  .callout.warn{background:var(--surface-2);border-color:var(--line-2)}
  .callout.info{background:var(--surface-2);border-color:var(--line-2)}
  .callout.good{background:var(--surface-2);border-color:var(--line-2)}
}

/* ─── tables ─────────────────────────────────────────────── */
.tablewrap{border:1px solid var(--line);border-radius:var(--r);overflow:auto;background:var(--surface);box-shadow:var(--shadow-s);margin-top:14px}
table{width:100%;border-collapse:collapse;font-size:14.3px;min-width:480px}
th,td{padding:13px 16px;text-align:left;border-bottom:1px solid var(--line)}
th{background:var(--surface-2);font-size:11px;letter-spacing:1.1px;text-transform:uppercase;color:var(--dim);font-weight:750}
tr:last-child td{border-bottom:none}
tbody tr{transition:background .2s}
tbody tr:hover{background:var(--surface-2)}
td.ctr,th.ctr{text-align:center}
.ok{color:var(--teal);font-weight:800}.nope{color:var(--rose);font-weight:800}

/* ─── PO stage demo ──────────────────────────────────────── */
.demo{border:1px solid var(--line);background:var(--surface);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-s);margin-top:16px}
.stage-tabs{display:flex;gap:8px;flex-wrap:wrap}
.stage-tabs button{
  flex:1 1 150px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:var(--surface-2);
  color:var(--mut);font-weight:650;font-size:13.6px;cursor:pointer;transition:.28s;font-family:inherit;text-align:left
}
.stage-tabs button small{display:block;font-size:11px;font-weight:600;color:var(--dim);letter-spacing:.6px;text-transform:uppercase}
.stage-tabs button:hover{border-color:var(--line-2);transform:translateY(-2px)}
.stage-tabs button.on{background:var(--grad);color:#fff;border-color:transparent;box-shadow:0 12px 24px -12px rgba(15,118,110,.9)}
.stage-tabs button.on small{color:rgba(255,255,255,.8)}
.stage-body{margin-top:18px;border-top:1px dashed var(--line);padding-top:18px;min-height:150px}
.stage-pane{display:none;animation:fade .45s cubic-bezier(.22,1,.36,1)}
.stage-pane.on{display:block}
@keyframes fade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.pill{display:inline-block;font-size:11px;font-weight:800;letter-spacing:1.1px;text-transform:uppercase;padding:4px 11px;border-radius:999px;margin-bottom:10px}
.pill.draft{background:color-mix(in srgb,var(--amber) 16%,transparent);color:var(--amber)}
.pill.recv{background:color-mix(in srgb,var(--blue) 15%,transparent);color:var(--blue)}
.pill.fin{background:color-mix(in srgb,var(--teal) 16%,transparent);color:var(--teal)}
.stage-meter{height:6px;border-radius:999px;background:var(--surface-3);margin-top:16px;overflow:hidden}
.stage-meter i{display:block;height:100%;width:33%;border-radius:999px;background:var(--grad);transition:width .55s cubic-bezier(.22,1,.36,1)}

/* ─── bars ───────────────────────────────────────────────── */
.bar{margin-top:14px}
.bar .lab{display:flex;justify-content:space-between;font-size:13.3px;color:var(--mut);margin-bottom:6px}
.bar .lab b{color:var(--ink);font-family:var(--mono)}
.bar .trk{height:11px;border-radius:999px;background:var(--surface-3);overflow:hidden}
.bar .fil{height:100%;width:0;border-radius:999px;transition:width 1.3s cubic-bezier(.22,1,.36,1)}
.fil.o{background:linear-gradient(90deg,#fbbf24,#d97706)}
.fil.r{background:var(--grad)}

/* ─── FIFO demo ──────────────────────────────────────────── */
.fifo-ctl{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.fifo-ctl input{
  width:92px;padding:10px 13px;border-radius:11px;border:1px solid var(--line-2);background:var(--surface-2);
  color:var(--ink);font-family:var(--mono);font-size:14px;font-weight:650
}
.fifo-ctl input:focus{outline:2px solid var(--teal);outline-offset:1px}
.lotrow{display:grid;grid-template-columns:repeat(auto-fit,minmax(152px,1fr));gap:11px}
.lotcard{
  border:1px solid var(--line);border-radius:13px;padding:13px 15px;background:var(--surface-2);position:relative;
  transition:.4s cubic-bezier(.22,1,.36,1);overflow:hidden
}
.lotcard.taking{border-color:var(--teal);box-shadow:0 0 0 3px color-mix(in srgb,var(--teal) 16%,transparent);transform:translateY(-3px)}
.lotcard.empty{opacity:.45}
.lotcard .nm{font-family:var(--mono);font-size:13px;font-weight:700;color:var(--teal)}
.lotcard .ex{font-size:11.5px;color:var(--dim);margin-top:1px}
.lotcard .qt{font-size:1.4rem;font-weight:800;margin-top:6px;letter-spacing:-.03em}
.lotcard .rt{font-size:11.5px;color:var(--mut);font-family:var(--mono)}
.lotcard .tagf{position:absolute;top:9px;right:10px;font-size:9px;font-weight:800;letter-spacing:.8px;color:#fff;background:var(--teal);padding:2px 7px;border-radius:999px}
.fifo-out{margin-top:16px;border-top:1px dashed var(--line);padding-top:14px;font-family:var(--mono);font-size:12.8px;color:var(--mut);min-height:64px}
.fifo-out .line{opacity:0;transform:translateX(-8px);animation:slide .4s forwards}
@keyframes slide{to{opacity:1;transform:none}}
.fifo-out .tot{color:var(--ink);font-weight:700;margin-top:6px;display:block}

/* ─── chain ──────────────────────────────────────────────── */
.chain{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin-top:16px}
.chain .n{
  flex:1 1 140px;border:1px solid var(--line);background:var(--surface);border-radius:13px;padding:14px;text-align:center;
  box-shadow:var(--shadow-s);transition:.3s;position:relative
}
.chain .n:hover{transform:translateY(-4px);border-color:var(--teal);box-shadow:var(--shadow-m)}
.chain .n small{font-size:10.5px;letter-spacing:1.1px;text-transform:uppercase;color:var(--dim);font-weight:750}
.chain .n b{display:block;font-size:13.4px;margin-top:5px;font-weight:650}
.chain .n:not(:last-child)::after{
  content:"→";position:absolute;right:-13px;top:50%;transform:translateY(-50%);color:var(--dim);font-size:15px;z-index:1
}
@media(max-width:700px){.chain .n::after{display:none!important}}

/* ─── help / contact ─────────────────────────────────────── */
.help{
  border-radius:var(--r-lg);padding:clamp(26px,5vw,48px);position:relative;overflow:hidden;margin-top:30px;
  border:1px solid var(--line);background:var(--surface);box-shadow:var(--shadow-m);text-align:center
}
.help::before{content:"";position:absolute;inset:0;background:var(--grad-soft)}
.help::after{
  content:"";position:absolute;width:460px;height:460px;left:-150px;bottom:-250px;border-radius:50%;
  background:radial-gradient(circle,rgba(37,99,235,.22),transparent 68%);animation:drift 22s ease-in-out infinite
}
.help > *{position:relative;z-index:1}
.avatar{
  width:72px;height:72px;border-radius:21px;margin:0 auto 16px;display:grid;place-items:center;
  background:var(--grad);color:#fff;font-family:"Times New Roman",Times,serif;font-weight:bold;font-size:25px;
  box-shadow:0 18px 40px -18px rgba(15,118,110,.95);animation:bob 4.5s ease-in-out infinite
}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.linkgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(222px,1fr));gap:12px;margin-top:26px;text-align:left}
.lcard{
  display:flex;align-items:center;gap:13px;padding:15px 17px;border-radius:14px;border:1px solid var(--line);
  background:var(--surface);box-shadow:var(--shadow-s);transition:.3s;color:inherit
}
.lcard:hover{transform:translateY(-4px);box-shadow:var(--shadow-m);border-color:var(--teal)}
.lcard .li{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:0 0 auto;background:var(--grad-soft);border:1px solid var(--line)}
.lcard .li svg{width:18px;height:18px;stroke:var(--teal);fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
.lcard b{display:block;font-size:14.4px;font-weight:700}
.lcard small{display:block;font-size:12.2px;color:var(--dim);font-family:var(--mono)}
.lcard .ar{margin-left:auto;color:var(--dim);transition:.3s}
.lcard:hover .ar{color:var(--teal);transform:translate(3px,-3px)}
.lcard.hot{border-color:var(--teal);background:var(--surface)}
.lcard.hot .li{background:var(--grad)}
.lcard.hot .li svg{stroke:#fff}

/* ─── footer ─────────────────────────────────────────────── */
footer{
  border-top:1px solid var(--line);margin-top:70px;padding:30px 0 0;text-align:center;color:var(--dim);font-size:13.2px
}
footer b{font-family:"Times New Roman",Times,serif;font-size:15.5px;color:var(--ink)}
footer a{font-weight:650}

.totop{
  position:fixed;right:20px;bottom:20px;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;z-index:70;
  border:1px solid var(--line);background:var(--surface);box-shadow:var(--shadow-m);cursor:pointer;color:var(--teal);
  opacity:0;pointer-events:none;transition:.3s;transform:translateY(8px)
}
.totop.show{opacity:1;pointer-events:auto;transform:none}
.totop:hover{transform:translateY(-3px)}
.totop svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round}

@media (prefers-reduced-motion:reduce){
  *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}
  .rv{opacity:1;transform:none}
  html{scroll-behavior:auto}
}
@media print{
  aside,.topbar,.totop,.hero-cta,.stage-tabs,.fifo-ctl{display:none!important}
  body{background:#fff}
  main{max-width:none;padding:0}
}
</style>
</head>
<body>

<!-- ═══════════ TOPBAR ═══════════ -->
<header class="topbar">
  <button class="iconbtn" id="menuBtn" aria-label="Menu">
    <svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
  </button>
  <a class="brand" href="#top">
    <span class="mark">SL</span>
    <span class="txt"><b>SL Diagnostics</b><span>Inventory &amp; Stock</span></span>
  </a>
  <div class="tb-right">
    <button class="iconbtn" id="themeBtn" aria-label="Toggle theme">
      <svg class="moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      <svg class="sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/></svg>
    </button>
    <a class="tb-cta" href="#help">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span>Help &amp; Support</span>
    </a>
  </div>
  <div class="scrollbar" id="sbar"></div>
</header>

<div class="backdrop" id="backdrop"></div>

<div class="shell">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="side">
  <div class="nav-group">
    <h5>Getting started</h5>
    <a href="#top"><span class="ni"><svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/></svg></span>Introduction</a>
    <a href="#overview"><span class="ni"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>What's inside</a>
    <a href="#structure"><span class="ni"><svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>Folder structure</a>
    <a href="#install"><span class="ni"><svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="m7 12 5 5 5-5"/><path d="M5 21h14"/></svg></span>Installation</a>
  </div>
  <div class="nav-group">
    <h5>Concepts</h5>
    <a href="#documents"><span class="ni"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/></svg></span>Document numbers</a>
    <a href="#roles"><span class="ni"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>Roles &amp; security</a>
    <a href="#stock"><span class="ni"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/></svg></span>Stock logic</a>
    <a href="#budget"><span class="ni"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 3-4 4 3 5-7"/></svg></span>Budget page</a>
  </div>
  <div class="nav-group">
    <h5>Customise</h5>
    <a href="#logo"><span class="ni"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.8"/><path d="m21 16-5-5L5 21"/></svg></span>Your logo</a>
    <a href="#notes"><span class="ni"><svg viewBox="0 0 24 24"><path d="M9 11h6"/><path d="M9 15h4"/><path d="M5 3h14v18l-7-3-7 3z"/></svg></span>Notes</a>
  </div>
  <div class="nav-group">
    <h5>Support</h5>
    <a href="#help"><span class="ni"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.6 2.6 0 0 1 5 1c0 1.7-2.5 2-2.5 3.5"/><path d="M12 17h.01"/></svg></span>Help &amp; contact</a>
  </div>

  <div class="sb-foot">
    <p>Built &amp; maintained by <b>SmartJOANS</b>. Setup, customisation or support?</p>
    <a class="mini" href="https://services.smartjoans.space" target="_blank" rel="noopener">services.smartjoans.space →</a>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main>

<!-- ── HERO ── -->
<section id="top" style="padding-top:0">
  <div class="hero rv in">
    <span class="tag"><span class="live"></span> Build v6 · Order → Receive (GRN)</span>
    <h1>Laboratory <em>Inventory &amp; Stock</em> Management System</h1>
    <p class="lede">
      Core PHP (PDO) + MySQL + Bootstrap 5 + vanilla JS. No framework, no composer, no build step —
      drop the folder into <code class="inl">htdocs</code> or <code class="inl">public_html</code> and it runs.
      Lot-wise valuation, a two-stage purchase workflow, FIFO issue vouchers and a live monthly budget.
    </p>
    <div class="hero-cta">
      <a class="btn p" href="#install">
        <svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="m7 12 5 5 5-5"/><path d="M5 21h14"/></svg>
        Install in 5 steps
      </a>
      <a class="btn g" href="#stock">
        <svg viewBox="0 0 24 24"><path d="M4 7h10"/><path d="m11 4 3 3-3 3"/><path d="M20 17H10"/><path d="m13 14-3 3 3 3"/></svg>
        How the stock logic works
      </a>
    </div>
    <div class="meta">
      <span>PHP 7.4+ · tested on 8.3</span><span>MySQL · PDO</span><span>Bootstrap 5.3</span>
      <span>Vanilla JS</span><span>XAMPP</span><span>cPanel / LiteSpeed</span><span>pdo_mysql</span>
    </div>
  </div>

  <div class="stats">
    <div class="stat rv" data-d="1"><b data-count="11">0</b><small>Tables</small></div>
    <div class="stat rv" data-d="2"><b data-count="1">0</b><small>SQL view</small></div>
    <div class="stat rv" data-d="3"><b data-count="3">0</b><small>Number series</small></div>
    <div class="stat rv" data-d="4"><b data-count="2">0</b><small>Stage workflow</small></div>
    <div class="stat rv" data-d="5"><b data-count="2">0</b><small>Roles</small></div>
    <div class="stat rv" data-d="6"><b data-count="0">0</b><small>Build steps</small></div>
  </div>
</section>

<!-- ── OVERVIEW ── -->
<section id="overview">
  <div class="sec-head rv">
    <span class="sec-label">What's inside</span>
    <h2>One system, purchase order to consumption</h2>
    <p class="sub">Clean database, Admin / User logins, your logo on the printed PO, and a purchase workflow
       where stock is created only after the delivery is physically checked in.</p>
  </div>

  <div class="grid g3">
    <div class="card rv" data-d="1">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M3 7h18"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="m5 7 1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13"/></svg></div>
      <h3>Item &amp; supplier masters</h3>
      <p>Every item carries its department, analyser, default supplier and item code. Lot tracking is a per-item switch — reagents on, gloves off.</p>
    </div>
    <div class="card rv" data-d="2">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="m9 11 3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg></div>
      <h3>Two-stage purchase</h3>
      <p>Ordering raises a Draft only. Receiving records what actually arrived — quantity, hiked rate, lot numbers, expiry — then Finalize posts the stock.</p>
    </div>
    <div class="card rv" data-d="3">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 3-4 4 3 5-7"/></svg></div>
      <h3>Exact valuation</h3>
      <p>No average costing. Each lot keeps the rate it was bought at, so the same item from three suppliers at three rates still values correctly.</p>
    </div>
    <div class="card rv" data-d="1">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M5 8h14"/><path d="M7 21h10"/><path d="M8 8c0 4-2 5-2 7a3 3 0 0 0 6 0c0-2-2-3-2-7"/></svg></div>
      <h3>Direct GRN</h3>
      <p>Urgent supply, free replacement, sample or an over-the-counter buy — goods received with no purchase order, posted to stock in one screen.</p>
    </div>
    <div class="card rv" data-d="2">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 13h6"/><path d="M9 17h4"/></svg></div>
      <h3>Issue vouchers</h3>
      <p>One slip, many items, FIFO or a lot you pick. All-or-nothing saving — a short line rolls the whole voucher back. Prints with lot numbers and signatures.</p>
    </div>
    <div class="card rv" data-d="3">
      <div class="ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 9v12"/></svg></div>
      <h3>Monthly budget</h3>
      <p>The budget sheet, live: opening → purchase → issues → closing → requirement, grouped by dept / equipment or supplier, with draft POs in one click.</p>
    </div>
    <div class="card rv" data-d="1">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
      <h3>Locked down</h3>
      <p>bcrypt hashes, CSRF on every form, prepared statements everywhere, server-side guards, idle timeout and a 5-attempt lockout.</p>
    </div>
    <div class="card rv" data-d="2">
      <div class="ico"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="M8 15h8"/><path d="M8 11h3"/></svg></div>
      <h3>Print &amp; export</h3>
      <p>A4 purchase order with your logo and goods-received comparison, issue slips, and CSV export from the stock report and the budget.</p>
    </div>
    <div class="card rv" data-d="3">
      <div class="ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
      <h3>Full audit trail</h3>
      <p>Who created the PO, who finalized it, who issued each line — names survive the deletion of that login and print under “Prepared By”.</p>
    </div>
  </div>
</section>

<!-- ── STRUCTURE ── -->
<section id="structure">
  <div class="sec-head rv">
    <span class="sec-label">Section 01</span>
    <h2>Folder structure</h2>
    <p class="sub">One flat application folder. Every page sets <code class="inl">$page_title</code> and
       <code class="inl">$active</code> and includes the shared header.</p>
  </div>

  <div class="tree rv">
<pre><span class="d">sl-inventory/</span>
├── <span class="h">database.sql</span>              <span class="c">Clean MySQL schema + ONE default admin login</span>
├── upgrade_v3.sql            <span class="c">ONLY for an older install with data to keep</span>
├── upgrade_v4.sql            <span class="c">ONLY for an older install: dept / equipment / budget</span>
├── upgrade_v5.sql            <span class="c">ONLY for an older install: issue voucher columns</span>
├── upgrade_v6.sql            <span class="c">ONLY for an older install: direct GRN flag</span>
├── <span class="h">logo.png</span>                  <span class="c">← PUT YOUR LOGO HERE</span>
├── <span class="h">db.php</span>                    <span class="c">App constants, session settings, PDO (EDIT THIS)</span>
├── login.php                 <span class="c">Sign in (split-panel on desktop, single column on mobile)</span>
├── logout.php                <span class="c">Sign out</span>
├── index.php                 <span class="c">Dashboard — stock value, low stock, expiry, POs to receive</span>
├── suppliers.php             <span class="c">Supplier master (CRUD)</span>
├── items.php                 <span class="c">Item master (CRUD): lot switch, dept, analyser, supplier</span>
├── departments.php           <span class="c">Departments + the equipment / analysers inside them</span>
├── budget.php                <span class="c">Monthly budget — the sheet, inside the system</span>
├── create_po.php             <span class="c">Raise / edit a Draft PO (AJAX rate auto-fill)</span>
├── receive_po.php            <span class="c">Receive (GRN) — qty, rate, lot numbers, then finalize</span>
├── direct_grn.php            <span class="c">Direct GRN — goods received with no purchase order</span>
├── po_list.php               <span class="c">PO register — ordered vs received, variance</span>
├── view_po.php               <span class="c">PO view + A4 print with logo + goods-received comparison</span>
├── stock_report.php          <span class="c">Stock &amp; valuation (lot-wise / item-wise) + CSV export</span>
├── stock_out.php             <span class="c">Issue voucher — many items per slip + issue register</span>
├── users.php                 <span class="c">User Management  ← ADMIN ONLY</span>
├── profile.php               <span class="c">My Account — own name + own password (both roles)</span>
├── <span class="d">includes/</span>
│   ├── functions.php         <span class="c">Helpers + ALL stock business logic</span>
│   ├── auth.php              <span class="c">Login session, roles, page guards</span>
│   ├── header.php            <span class="c">&lt;head&gt;, sidebar, topbar  (set $page_title, $active)</span>
│   └── footer.php            <span class="c">Closing markup + scripts</span>
├── <span class="d">ajax/</span>
│   ├── get_last_rate.php     <span class="c">?item_id=&amp;supplier_id= → last purchase rate + item info</span>
│   └── get_batches.php       <span class="c">?item_id= → available lots in FIFO order</span>
└── <span class="d">assets/</span>
    ├── css/style.css         <span class="c">Theme, login page, responsive sidebar, print stylesheet</span>
    └── js/app.js             <span class="c">Shared JS (number formatting, fetch wrapper, confirms)</span></pre>
  </div>
</section>

<!-- ── INSTALL ── -->
<section id="install">
  <div class="sec-head rv">
    <span class="sec-label">Section 02</span>
    <h2>Installation</h2>
    <p class="sub">Copy, import, edit four constants, log in. Requires PHP 7.4+ (tested on PHP 8.3) with
       <code class="inl">pdo_mysql</code>.</p>
  </div>

  <div class="steps" id="steps">
    <div class="rail"><i id="rail"></i></div>

    <div class="stp rv">
      <div class="dot">1</div>
      <h3>Drop the folder in place</h3>
      <p>Copy <code class="inl">sl-inventory</code> into <code class="inl">htdocs</code> (XAMPP) or
         <code class="inl">public_html</code> (cPanel). No composer, no build step.</p>
    </div>

    <div class="stp rv">
      <div class="dot">2</div>
      <h3>Import the database</h3>
      <p>It creates <code class="inl">sl_inventory</code>, all eleven tables, the <code class="inl">v_item_stock</code>
         view and <b>one</b> row — the default administrator. No suppliers, no items, no stock: you build the masters
         from inside the app.</p>
      <div class="codebox">
        <div class="codebar"><span class="nm">shell</span><button class="cp">Copy</button></div>
<pre><span class="c-c"># phpMyAdmin → Import, or from the shell:</span>
mysql -u root -p &lt; <span class="c-s">database.sql</span></pre>
      </div>
    </div>

    <div class="stp rv">
      <div class="dot">3</div>
      <h3>Edit the top of <code class="inl">db.php</code></h3>
      <div class="codebox">
        <div class="codebar"><span class="nm">db.php</span><button class="cp">Copy</button></div>
<pre><span class="c-k">define</span>(<span class="c-s">'DB_HOST'</span>, <span class="c-s">'localhost'</span>);
<span class="c-k">define</span>(<span class="c-s">'DB_NAME'</span>, <span class="c-s">'sl_inventory'</span>);
<span class="c-k">define</span>(<span class="c-s">'DB_USER'</span>, <span class="c-s">'root'</span>);      <span class="c-c">// cPanel: your DB user</span>
<span class="c-k">define</span>(<span class="c-s">'DB_PASS'</span>, <span class="c-s">''</span>);          <span class="c-c">// cPanel: your DB password</span></pre>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>Constant</th><th>Purpose</th></tr></thead>
          <tbody>
            <tr><td><code class="inl">APP_ADDRESS</code> / <code class="inl">APP_GST</code></td><td>Printed on the purchase order</td></tr>
            <tr><td><code class="inl">SESSION_IDLE_MINUTES</code></td><td>Idle timeout — default <b>240</b></td></tr>
            <tr><td><code class="inl">LOGIN_MAX_ATTEMPTS</code></td><td>Brute-force brake — default <b>5</b></td></tr>
            <tr><td><code class="inl">LOGIN_LOCK_MINUTES</code></td><td>Lockout duration — default <b>5</b></td></tr>
            <tr><td><code class="inl">DOC_PREFIX</code> / <code class="inl">GRN_PREFIX</code> / <code class="inl">ISSUE_PREFIX</code></td><td>The three document number series</td></tr>
            <tr><td><code class="inl">APP_LOGO</code></td><td>Logo file name — default <code class="inl">logo.png</code></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="stp rv">
      <div class="dot">4</div>
      <h3>Log in</h3>
      <p>Open <code class="inl">login.php</code> — username <code class="inl">admin</code>, password
         <code class="inl">admin123</code>.</p>
      <div class="callout warn">
        <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <p><b>Change this immediately.</b> Log in, open <b>My Account</b> and set a new password.
           Passwords are stored as bcrypt hashes — never in plain text.</p>
      </div>
    </div>

    <div class="stp rv">
      <div class="dot">5</div>
      <h3>First-run order</h3>
      <p>The dashboard shows a <b>Getting Started</b> checklist until the masters have data, and lists every PO still
         waiting to be received.</p>
      <div class="codebox">
        <div class="codebar"><span class="nm">workflow</span><button class="cp">Copy</button></div>
<pre>Login → Suppliers → Item Master → Create PO <span class="c-c">(saved as Draft)</span>
      → when the goods arrive: <span class="c-n">Receive (GRN)</span> → <span class="c-n">Finalize</span> → Stock Out</pre>
      </div>
    </div>
  </div>

  <div class="callout info rv">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/></svg>
    <p><b>Already running an older build?</b> Run <code class="inl">upgrade_v3.sql</code>,
       <code class="inl">upgrade_v4.sql</code>, <code class="inl">upgrade_v5.sql</code> and
       <code class="inl">upgrade_v6.sql</code> in that order instead of re-importing — together they add the receipt
       tables, the department / equipment layer, the budget table, the issue voucher columns and the direct GRN flag
       while keeping your data. A fresh install needs none of them.</p>
  </div>
</section>

<!-- ── DOCUMENTS ── -->
<section id="documents">
  <div class="sec-head rv">
    <span class="sec-label">Section 03</span>
    <h2>Three documents, three number series</h2>
    <p class="sub">All defined at the top of <code class="inl">db.php</code>, so renaming any of them is a one-line
       change that every page follows.</p>
  </div>

  <div class="tablewrap rv">
    <table>
      <thead><tr><th>Document</th><th>Number</th><th>What it is</th></tr></thead>
      <tbody>
        <tr><td><b>Purchase Order</b><br><code class="inl">DOC_PREFIX</code></td><td><code class="inl">PO/2026-27/0001</code></td><td>What we ordered. Always a Draft until the goods arrive.</td></tr>
        <tr><td><b>Goods Receive Note</b><br><code class="inl">GRN_PREFIX</code></td><td><code class="inl">GRN/2026-27/0001</code></td><td>Goods received <b>without</b> a purchase order — <code class="inl">direct_grn.php</code>.</td></tr>
        <tr><td><b>Issue Voucher</b><br><code class="inl">ISSUE_PREFIX</code></td><td><code class="inl">ISS/2026-27/0001</code></td><td>Stock issued to a department.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="callout good rv">
    <svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
    <p>Receiving against a purchase order keeps that order's own <code class="inl">PO/…</code> number — the received
       copy of the order <i>is</i> its goods receive note, which is why the printed sheet is headed
       <b>“Purchase Order”</b> while it is a Draft and <b>“Goods Receive Note”</b> once it has been received.</p>
  </div>
</section>

<!-- ── ROLES ── -->
<section id="roles">
  <div class="sec-head rv">
    <span class="sec-label">Section 04</span>
    <h2>Roles &amp; security</h2>
    <p class="sub">A User can use every page except <code class="inl">users.php</code>; only an Admin can create logins.
       The guard is server-side, so typing the URL directly does not get a User in.</p>
  </div>

  <div class="tablewrap rv">
    <table>
      <thead><tr><th>Page</th><th class="ctr">Admin</th><th class="ctr">User</th></tr></thead>
      <tbody>
        <tr><td>Dashboard, Suppliers, Item Master, Departments</td><td class="ctr ok">✔</td><td class="ctr ok">✔</td></tr>
        <tr><td>Monthly Budget, requirement, draft POs from it</td><td class="ctr ok">✔</td><td class="ctr ok">✔</td></tr>
        <tr><td>Create PO, Purchase Orders, Final conversion, Print</td><td class="ctr ok">✔</td><td class="ctr ok">✔</td></tr>
        <tr><td>Stock &amp; Valuation, CSV export, Stock Out, Reverse</td><td class="ctr ok">✔</td><td class="ctr ok">✔</td></tr>
        <tr><td>My Account (own name / password)</td><td class="ctr ok">✔</td><td class="ctr ok">✔</td></tr>
        <tr><td><b>User Management</b> — create logins, set roles</td><td class="ctr ok">✔</td><td class="ctr nope">✖</td></tr>
      </tbody>
    </table>
  </div>

  <div class="grid g2" style="margin-top:16px">
    <div class="card rv" data-d="1">
      <h3>Guards that cannot be overridden</h3>
      <ul class="tidy">
        <li><code class="inl">require_login()</code> on every page, <code class="inl">require_admin()</code> on <code class="inl">users.php</code></li>
        <li>You cannot delete or deactivate your own login</li>
        <li>The <b>last active Admin</b> cannot be deleted, deactivated or demoted — the system can never be locked out</li>
        <li>Ajax endpoints answer <code class="inl">401</code> to a dead session instead of leaking data</li>
        <li>Every PO stores who created and who finalized it; every stock-out line stores who issued it — names survive the deletion of that login</li>
      </ul>
    </div>
    <div class="card rv" data-d="2">
      <h3>Security built in</h3>
      <ul class="tidy">
        <li>bcrypt <code class="inl">password_hash</code> — never plain text</li>
        <li>CSRF token on every form</li>
        <li>Prepared statements everywhere · <code class="inl">h()</code> on all output</li>
        <li>HttpOnly + SameSite=Lax cookie (Secure automatically over HTTPS)</li>
        <li>Session ID regenerated on login · idle timeout</li>
        <li>5-attempt lockout · whitelist on the post-login redirect</li>
      </ul>
    </div>
  </div>
</section>

<!-- ── STOCK ── -->
<section id="stock">
  <div class="sec-head rv">
    <span class="sec-label">Section 05</span>
    <h2>How the stock logic works</h2>
    <p class="sub">Every rupee in the valuation traces back to a lot, and every lot traces back to the receipt that
       created it.</p>
  </div>

  <div class="grid g2">
    <div class="card rv" data-d="1">
      <h3>Multi-lot items</h3>
      <p><code class="inl">items.has_lot = 1</code> means every purchase creates or updates a row in
         <code class="inl">item_batches</code> carrying its own <code class="inl">lot_number</code>,
         <code class="inl">expiry_date</code>, <code class="inl">supplier_id</code> and <code class="inl">unit_price</code>.</p>
      <p><code class="inl">has_lot = 0</code> items — gloves, tubes, stationery — sit under the reserved lot
         <code class="inl">GENERAL</code> with a NULL expiry; the PO form hides those two fields automatically.</p>
    </div>
    <div class="card rv" data-d="2">
      <h3>Valuation — no average costing</h3>
      <p>Each lot keeps the rate it was bought at, so the total is exact even when the same item was bought from three
         suppliers at three rates.</p>
      <div class="codebox">
        <div class="codebar"><span class="nm">valuation</span></div>
<pre><span class="c-fn">Current Total Stock Value</span> =
  SUM(item_batches.<span class="c-s">quantity_available</span>
      * item_batches.<span class="c-s">unit_price</span>)</pre>
      </div>
    </div>
  </div>

  <!-- interactive PO stages -->
  <h3 class="rv" style="margin-top:34px;font-size:1.2rem">Order first, receive second — try the three stages</h3>
  <p class="sub rv" style="margin-top:6px">A purchase order can <b>never</b> be finalized straight from the order screen.</p>

  <div class="demo rv">
    <div class="stage-tabs" id="stageTabs">
      <button class="on" data-s="0">Order<small>create_po.php</small></button>
      <button data-s="1">Receive (GRN)<small>receive_po.php</small></button>
      <button data-s="2">Finalize<small>finalize_po()</small></button>
    </div>
    <div class="stage-body">
      <div class="stage-pane on" data-p="0">
        <span class="pill draft">Status · Draft</span>
        <h3>What we asked for</h3>
        <ul class="tidy">
          <li>Item, quantity and expected rate — the rate auto-fills from the last <b>received</b> rate</li>
          <li><code class="inl">item_batches</code> untouched — nothing enters the valuation</li>
          <li>Fully editable and deletable · no lot numbers asked for at this stage</li>
        </ul>
      </div>
      <div class="stage-pane" data-p="1">
        <span class="pill recv">Status · Draft (receipt saved)</span>
        <h3>What actually arrived</h3>
        <ul class="tidy">
          <li>Correct the quantity, and the rate if the supplier hiked it</li>
          <li>Enter lot numbers and expiry dates — split one ordered line across several lots</li>
          <li>Can be <b>saved without finalizing</b> for a part delivery — the PO stays Draft</li>
          <li>Each line shows received qty and value against the ordered figures before you commit</li>
        </ul>
      </div>
      <div class="stage-pane" data-p="2">
        <span class="pill fin">Status · Final</span>
        <h3>Stock is created — once</h3>
        <ul class="tidy">
          <li><code class="inl">finalize_po()</code> posts the confirmed receipt rows into <code class="inl">item_batches</code></li>
          <li>Transaction-wrapped, flipped with <code class="inl">UPDATE … WHERE id = ? AND status = 'Draft'</code> — a double-click or two users at once can never post twice</li>
          <li>Finalizing with no confirmed receipt rows is refused</li>
          <li>The PO becomes read-only and prints headed <b>Goods Receive Note</b></li>
        </ul>
      </div>
    </div>
    <div class="stage-meter"><i id="stageMeter"></i></div>
  </div>

  <div class="grid g2" style="margin-top:16px">
    <div class="card rv" data-d="1">
      <h3>Ordered and received never overwrite each other</h3>
      <p>A PO ordered at ₹1,000 and delivered 8 of 10 at a hiked rate of ₹110 keeps the ₹1,000 order on record and posts
         ₹880 of stock.</p>
      <div id="bars">
        <div class="bar">
          <div class="lab"><span>Ordered — <code class="inl">po_items</code></span><b>₹1,000</b></div>
          <div class="trk"><div class="fil o" data-w="100"></div></div>
        </div>
        <div class="bar">
          <div class="lab"><span>Received — <code class="inl">po_receipts</code></span><b>₹880</b></div>
          <div class="trk"><div class="fil r" data-w="88"></div></div>
        </div>
      </div>
      <p style="margin-top:12px">The difference shows on the PO register, the PO view and the printed sheet.
         <b>Stock is always valued at the rate actually received.</b></p>
    </div>

    <div class="card rv" data-d="2">
      <h3>Goods that arrive without an order</h3>
      <p><code class="inl">direct_grn.php</code> — an urgent supply, a free replacement, a sample, something bought over
         the counter. One screen: GRN number, supplier, date, invoice number, then the items with their lot, expiry,
         quantity and rate. Saving posts the stock immediately — no draft, no separate receive step.</p>
      <p>Behind the scenes it is stored exactly like a received purchase order
         (<code class="inl">purchase_orders.is_direct = 1</code>) with the ordered lines mirroring what was received, so
         it shows no variance — and the stock report, the valuation, the last-rate lookup and the budget all count it the
         same as any other receipt.</p>
    </div>
  </div>

  <!-- FIFO demo -->
  <h3 class="rv" style="margin-top:34px;font-size:1.2rem">Stock out — FIFO, live</h3>
  <p class="sub rv" style="margin-top:6px">Earliest expiry first, lots without an expiry last, spanning as many lots as
     needed. Every slice writes a <code class="inl">stock_out</code> row with that lot's own rate.</p>

  <div class="demo rv">
    <div class="fifo-ctl">
      <input id="qty" type="number" min="1" max="60" value="7" aria-label="Quantity to issue">
      <button class="btn p" id="issueBtn">
        <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        Issue
      </button>
      <button class="btn g" id="resetBtn">
        <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
        Reset
      </button>
    </div>
    <div class="lotrow" id="lotRow"></div>
    <div class="fifo-out" id="fifoOut"><span style="color:var(--dim)">Enter a quantity and press Issue to watch the lots deplete.</span></div>
  </div>

  <div class="grid g2" style="margin-top:16px">
    <div class="card rv" data-d="1">
      <h3>One voucher, all or nothing</h3>
      <p>A date, a department, a voucher number and the person who collected the goods, then as many items as needed
         underneath. The number auto-fills as <code class="inl">ISS/2026-27/0001</code> but stays editable so it can match
         your register book.</p>
      <p><code class="inl">issue_voucher()</code> wraps the whole slip in one transaction — if the third item is short of
         stock, the first two are rolled back too and nothing is issued. Short quantities are rejected in full, never
         partially.</p>
      <p>The voucher prints with your logo, the lot numbers, the values and signature lines for <i>Issued By</i> and
         <i>Received By</i>. In the register each voucher is a block, reversible one line at a time or whole — the quantity
         goes back to the same lot.</p>
    </div>
    <div class="card rv" data-d="2">
      <h3>Rate auto-fill &amp; lot merging</h3>
      <p>Picking an item in the PO form calls <code class="inl">ajax/get_last_rate.php</code>: last <b>received</b> rate for
         that item <i>and</i> supplier → last received rate from any supplier → opening-stock rate. The field stays
         editable; once you type in it, changing the supplier will not overwrite your value.</p>
      <p>A received line merges into an existing lot only when item + supplier + lot + expiry + rate <b>all</b> match; any
         difference opens a new lot row, so valuation stays accurate. Each receipt row keeps
         <code class="inl">batch_id</code>, so every stock lot traces back to the exact PO line it came from.</p>
    </div>
  </div>
</section>

<!-- ── BUDGET ── -->
<section id="budget">
  <div class="sec-head rv">
    <span class="sec-label">Section 06</span>
    <h2>The Budget page</h2>
    <p class="sub"><code class="inl">budget.php</code> is the monthly stock budget sheet, live. Pick a month and it shows,
       per item, the full chain — grouped <b>Dept / Equipment</b> or <b>Supplier wise</b>, with sub-totals per group and a
       grand total.</p>
  </div>

  <div class="chain rv">
    <div class="n"><small>Opening</small><b>last close</b></div>
    <div class="n"><small>Purchase</small><b>PO receipts</b></div>
    <div class="n"><small>Issues</small><b>Stock Out</b></div>
    <div class="n"><small>Closing</small><b>O + P − I</b></div>
    <div class="n"><small>Required</small><b>next month</b></div>
  </div>

  <div class="grid g2" style="margin-top:18px">
    <div class="card rv" data-d="1">
      <h3>What you can do on it</h3>
      <ul class="tidy">
        <li><b>Required qty</b> is editable per item. <i>Fill suggested</i> writes
            <code class="inl">issues × cover − closing</code> into every blank row, where <i>cover</i> (default 2 months) is
            yours to set. The rate is editable too.</li>
        <li><b>Save Requirement</b> stores it against the month.</li>
        <li><b>Close month</b> freezes the figures so that month's closing becomes the next month's opening — what keeps
            the chain going, the same way one sheet tab feeds the next.</li>
        <li><b>Create Draft POs (supplier wise)</b> turns the saved requirement into one Draft purchase order per supplier,
            using each item's default supplier. Items with no supplier set are skipped and listed.</li>
        <li><b>Export</b> gives the same table as CSV, so the monthly file can still be mailed as before.</li>
      </ul>
    </div>
    <div class="card rv" data-d="2">
      <h3>Where the numbers come from</h3>
      <p>The system works every figure out from its own data — purchases from the PO receipts, issues from the Stock Out
         register, and <code class="inl">closing = opening + purchase − issues</code>.</p>
      <p>Departments and analysers are maintained in <code class="inl">departments.php</code>, and every Stock Out entry
         picks a department from that list — which is what makes the department-wise consumption in the budget accurate.</p>
      <div class="callout good" style="margin-top:14px">
        <svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
        <p>Nothing enters stock from the budget page — the draft POs it raises still have to be received and finalized.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── LOGO ── -->
<section id="logo">
  <div class="sec-head rv">
    <span class="sec-label">Section 07</span>
    <h2>Your logo</h2>
    <p class="sub">Put <code class="inl">logo.png</code> in the application root, next to <code class="inl">db.php</code>.</p>
  </div>

  <div class="grid g2">
    <div class="card rv" data-d="1">
      <h3>Used automatically on</h3>
      <ul class="tidy">
        <li>The <b>printed purchase order</b> header — left side, beside the address / GSTIN</li>
        <li>The <b>login page</b> — on a white panel, so a dark or coloured logo still reads</li>
        <li>The <b>sidebar</b> brand area</li>
        <li>The <b>browser tab</b> icon</li>
      </ul>
    </div>
    <div class="card rv" data-d="2">
      <h3>Sizing &amp; fallback</h3>
      <p>Nothing breaks if the file is missing — every spot falls back to the text wordmark
         <b style="font-family:'Times New Roman',Times,serif">SL Diagnostics</b> in Times New Roman Bold.</p>
      <p>A wide logo that already contains the full name works best: capped at <b>64px</b> tall on the PO,
         <b>66px</b> on login and <b>46px</b> in the sidebar, width scales automatically. Replacing the file is enough —
         the <code class="inl">?v=</code> on the URL is the file's own modified time, so the new logo appears immediately
         instead of a cached one. For a different file name, change <code class="inl">APP_LOGO</code> in
         <code class="inl">db.php</code>.</p>
    </div>
  </div>
</section>

<!-- ── NOTES ── -->
<section id="notes">
  <div class="sec-head rv">
    <span class="sec-label">Section 08</span>
    <h2>Notes</h2>
    <p class="sub">Small things that save time later.</p>
  </div>

  <div class="grid g2">
    <div class="card rv" data-d="1">
      <h3>Going CDN-free</h3>
      <p>Bootstrap 5.3 and Bootstrap Icons load from jsDelivr. To run fully offline, download
         <code class="inl">bootstrap.min.css</code>, <code class="inl">bootstrap.bundle.min.js</code> and the icons font
         into <code class="inl">assets/</code> and change the tags in <code class="inl">includes/header.php</code>,
         <code class="inl">includes/footer.php</code> and <code class="inl">login.php</code>.</p>
    </div>
    <div class="card rv" data-d="2">
      <h3>Entering opening stock</h3>
      <p>Either raise one PO per supplier with the current rates and receive it with the real lot numbers
         (recommended — it keeps the audit trail), or insert the rows straight into
         <code class="inl">item_batches</code>. Rows entered without a real lot number can carry a placeholder and be
         corrected later:</p>
      <div class="codebox">
        <div class="codebar"><span class="nm">sql</span><button class="cp">Copy</button></div>
<pre><span class="c-k">UPDATE</span> item_batches
   <span class="c-k">SET</span> lot_number  = <span class="c-s">'AS123'</span>,
       expiry_date = <span class="c-s">'2027-06-30'</span>
 <span class="c-k">WHERE</span> item_id = <span class="c-n">12</span>
   <span class="c-k">AND</span> lot_number = <span class="c-s">'OPENING'</span>;</pre>
      </div>
    </div>
    <div class="card rv" data-d="1">
      <h3>Editing &amp; deleting</h3>
      <ul class="tidy">
        <li>Editing a Draft PO after a receipt has been saved against it <b>clears that saved receipt</b>, since the ordered lines it referred to are replaced.</li>
        <li>Deletions are protected by referential checks: a supplier with POs, an item used in a PO or still holding stock, and any Final PO cannot be deleted — <b>deactivate instead</b>.</li>
      </ul>
    </div>
    <div class="card rv" data-d="2">
      <h3>Columns beyond the original spec</h3>
      <p><code class="inl">is_active</code> on suppliers / items / users · <code class="inl">created_by</code>,
         <code class="inl">finalized_by</code>, <code class="inl">finalized_at</code>,
         <code class="inl">received_total</code>, <code class="inl">receive_date</code>,
         <code class="inl">invoice_no</code>, <code class="inl">receive_notes</code> on
         <code class="inl">purchase_orders</code> · <code class="inl">po_id</code> on
         <code class="inl">item_batches</code> (PO → lot traceability) · <code class="inl">issued_by</code> on
         <code class="inl">stock_out</code> · plus the <code class="inl">users</code>,
         <code class="inl">po_receipts</code> and <code class="inl">stock_out</code> tables and the
         <code class="inl">v_item_stock</code> view.</p>
      <p><code class="inl">po_items.lot_number</code> / <code class="inl">expiry_date</code> remain in the schema but are
         no longer filled by the order screen — lot details live in <code class="inl">po_receipts</code>.</p>
    </div>
  </div>
</section>

<!-- ── HELP ── -->
<section id="help">
  <div class="sec-head rv">
    <span class="sec-label">Support</span>
    <h2>Help &amp; contact</h2>
    <p class="sub">Installation help, customisation, a new module, a bug, or a build of your own — reach out and it gets
       handled.</p>
  </div>

  <div class="help rv">
    <div class="avatar">SJ</div>
    <h2 style="font-size:1.55rem">SmartJOANS</h2>
    <p class="sub" style="margin:12px auto 0;max-width:58ch">
      Full-stack web development — PHP / MySQL systems, ASP.NET, Node.js services and digital tools.
      This inventory system is one of a family of products built for laboratories and for the web.
    </p>

    <div class="linkgrid">
      <a class="lcard hot" href="https://services.smartjoans.space" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4.5 4.5 0 0 0 6 6l-9.3 9.3a2.8 2.8 0 0 1-4-4z"/><path d="m5 5 3 3"/></svg></span>
        <span><b>Support &amp; Services</b><small>services.smartjoans.space</small></span>
        <span class="ar">↗</span>
      </a>
      <a class="lcard" href="https://www.instagram.com/smart_joans" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg></span>
        <span><b>Instagram</b><small>@smart_joans</small></span>
        <span class="ar">↗</span>
      </a>
      <a class="lcard" href="https://www.smartjoans.space" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/></svg></span>
        <span><b>SmartJOANS</b><small>www.smartjoans.space</small></span>
        <span class="ar">↗</span>
      </a>
      <a class="lcard" href="https://books.smartjoans.space" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><path d="M4 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4z"/><path d="M20 4h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg></span>
        <span><b>SmartLib</b><small>books.smartjoans.space</small></span>
        <span class="ar">↗</span>
      </a>
      <a class="lcard" href="https://sharable.smartjoans.space" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/></svg></span>
        <span><b>Sharable</b><small>sharable.smartjoans.space</small></span>
        <span class="ar">↗</span>
      </a>
      <a class="lcard" href="https://myprofile.cc" target="_blank" rel="noopener">
        <span class="li"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2.2"/><path d="M5.5 17c.8-2 2-3 3.5-3s2.7 1 3.5 3"/><path d="M15 9h4"/><path d="M15 13h4"/></svg></span>
        <span><b>myprofile.cc</b><small>digital CV platform</small></span>
        <span class="ar">↗</span>
      </a>
    </div>

    <p style="margin-top:24px;font-size:12.8px;color:var(--dim)">
      Also built: <a href="https://artintentions.in" target="_blank" rel="noopener">artintentions.in</a>
      · <a href="https://writerpraba.co.in" target="_blank" rel="noopener">writerpraba.co.in</a>
    </p>
  </div>
</section>

<footer>
  <p><b>SL Diagnostics</b> — Laboratory Inventory &amp; Stock Management System</p>
  <p style="margin-top:4px">Core PHP · MySQL · Bootstrap 5 · No build step</p>
  <p style="margin-top:10px">
    Built by <a href="https://www.smartjoans.space" target="_blank" rel="noopener">SmartJOANS</a> ·
    <a href="https://services.smartjoans.space" target="_blank" rel="noopener">services.smartjoans.space</a> ·
    <a href="https://www.instagram.com/smart_joans" target="_blank" rel="noopener">@smart_joans</a>
  </p>
  <p style="margin-top:14px;font-size:12px">&copy; <span id="yr">2026</span> SmartJOANS. All rights reserved.</p>
</footer>

</main>
</div>

<button class="totop" id="toTop" aria-label="Back to top">
  <svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
</button>

<script>
(function(){
  "use strict";
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;

  /* ── theme ───────────────────────────────── */
  var saved = null;
  try{ saved = localStorage.getItem('sl-doc-theme'); }catch(e){}
  if(!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) saved = 'dark';
  if(saved) root.setAttribute('data-theme', saved);
  document.getElementById('themeBtn').addEventListener('click', function(){
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try{ localStorage.setItem('sl-doc-theme', next); }catch(e){}
  });

  /* ── mobile sidebar ──────────────────────── */
  var side = document.getElementById('side'), bd = document.getElementById('backdrop');
  function closeSide(){ side.classList.remove('open'); bd.classList.remove('on'); }
  document.getElementById('menuBtn').addEventListener('click', function(){
    side.classList.toggle('open'); bd.classList.toggle('on');
  });
  bd.addEventListener('click', closeSide);
  side.addEventListener('click', function(e){ if(e.target.closest('a')) closeSide(); });

  /* ── reveal ──────────────────────────────── */
  var rvs = document.querySelectorAll('.rv');
  if('IntersectionObserver' in window && !reduce){
    var io = new IntersectionObserver(function(es){
      es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
    },{threshold:.1,rootMargin:'0px 0px -50px 0px'});
    rvs.forEach(function(el){ io.observe(el); });
  } else { rvs.forEach(function(el){ el.classList.add('in'); }); }

  /* ── count up ────────────────────────────── */
  function up(el){
    var t = parseFloat(el.getAttribute('data-count')) || 0;
    if(reduce || t === 0){ el.textContent = t; return; }
    var d = 1100, s = null;
    function f(ts){
      if(!s) s = ts;
      var p = Math.min((ts-s)/d,1), e = 1-Math.pow(1-p,3);
      el.textContent = Math.round(t*e);
      if(p<1) requestAnimationFrame(f); else el.textContent = t;
    }
    requestAnimationFrame(f);
  }
  var cs = document.querySelectorAll('[data-count]');
  if('IntersectionObserver' in window){
    var io2 = new IntersectionObserver(function(es){
      es.forEach(function(e){ if(e.isIntersecting){ up(e.target); io2.unobserve(e.target); } });
    },{threshold:.5});
    cs.forEach(function(c){ io2.observe(c); });
  } else { cs.forEach(up); }

  /* ── variance bars ───────────────────────── */
  var bars = document.getElementById('bars');
  function fillBars(){
    bars.querySelectorAll('.fil').forEach(function(f,i){
      setTimeout(function(){ f.style.width = f.getAttribute('data-w') + '%'; }, 150 + i*280);
    });
  }
  if(bars && 'IntersectionObserver' in window){
    var io3 = new IntersectionObserver(function(es){
      es.forEach(function(e){ if(e.isIntersecting){ fillBars(); io3.unobserve(e.target); } });
    },{threshold:.4});
    io3.observe(bars);
  } else if(bars){ fillBars(); }

  /* ── install rail ────────────────────────── */
  var steps = document.getElementById('steps'), rail = document.getElementById('rail');
  var stps = steps ? steps.querySelectorAll('.stp') : [];
  function paintRail(){
    if(!steps || !rail) return;
    var mid = window.innerHeight * 0.6, r = steps.getBoundingClientRect();
    stps.forEach(function(s){
      if(s.getBoundingClientRect().top < mid) s.classList.add('hit');
    });
    rail.style.height = Math.max(0, Math.min(mid - r.top, r.height - 28)) + 'px';
  }

  /* ── scroll: progress, active nav, to-top ── */
  var sbar = document.getElementById('sbar'), toTop = document.getElementById('toTop');
  var links = Array.prototype.slice.call(document.querySelectorAll('.nav-group a'));
  var secs = links.map(function(a){ return document.querySelector(a.getAttribute('href')); });
  var tick = false;
  function onScroll(){
    var y = window.pageYOffset || root.scrollTop;
    var h = document.body.scrollHeight - window.innerHeight;
    sbar.style.width = (h > 0 ? (y/h)*100 : 0) + '%';
    toTop.classList.toggle('show', y > 600);
    var cur = 0;
    secs.forEach(function(s,i){ if(s && s.getBoundingClientRect().top <= 120) cur = i; });
    links.forEach(function(a,i){ a.classList.toggle('on', i === cur); });
    paintRail();
    tick = false;
  }
  window.addEventListener('scroll', function(){ if(!tick){ tick = true; requestAnimationFrame(onScroll); } }, {passive:true});
  window.addEventListener('resize', onScroll);
  onScroll();
  toTop.addEventListener('click', function(){ window.scrollTo({top:0, behavior: reduce ? 'auto' : 'smooth'}); });

  /* ── copy buttons ────────────────────────── */
  document.querySelectorAll('.cp').forEach(function(b){
    b.addEventListener('click', function(){
      var box = b.closest('.codebox'), pre = box && box.querySelector('pre');
      if(!pre) return;
      var txt = pre.innerText;
      var done = function(){
        b.textContent = 'Copied'; b.classList.add('ok');
        setTimeout(function(){ b.textContent = 'Copy'; b.classList.remove('ok'); }, 1500);
      };
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(txt).then(done, function(){ legacy(txt, done); });
      } else legacy(txt, done);
    });
  });
  function legacy(t, cb){
    var ta = document.createElement('textarea');
    ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); cb(); }catch(e){}
    document.body.removeChild(ta);
  }

  /* ── PO stage tabs ───────────────────────── */
  var tabs = document.getElementById('stageTabs');
  var panes = document.querySelectorAll('.stage-pane');
  var meter = document.getElementById('stageMeter');
  if(tabs){
    tabs.addEventListener('click', function(e){
      var b = e.target.closest('button'); if(!b) return;
      var s = parseInt(b.getAttribute('data-s'), 10);
      tabs.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on', x === b); });
      panes.forEach(function(p){ p.classList.toggle('on', parseInt(p.getAttribute('data-p'),10) === s); });
      meter.style.width = ((s+1)/3*100) + '%';
    });
  }

  /* ── FIFO demo ───────────────────────────── */
  var SEED = [
    { lot:'AS123',   exp:'exp 06/2027', qty:5,  rate:110 },
    { lot:'AR123',   exp:'exp 09/2027', qty:5,  rate:105 },
    { lot:'GENERAL', exp:'no expiry',   qty:40, rate:98  }
  ];
  var lots = JSON.parse(JSON.stringify(SEED));
  var row = document.getElementById('lotRow'), out = document.getElementById('fifoOut');
  function inr(n){
    var s = (Math.round(n*100)/100).toFixed(2), p = s.split('.'), i = p[0];
    var l3 = i.slice(-3), rest = i.slice(0,-3);
    if(rest) l3 = ',' + l3;
    return rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + l3 + '.' + p[1];
  }
  function draw(takingIdx){
    row.innerHTML = '';
    lots.forEach(function(l, i){
      var d = document.createElement('div');
      d.className = 'lotcard' + (l.qty === 0 ? ' empty' : '') + (i === takingIdx ? ' taking' : '');
      d.innerHTML = '<div class="nm">' + l.lot + '</div>' +
                    '<div class="ex">' + l.exp + '</div>' +
                    '<div class="qt">' + l.qty + '</div>' +
                    '<div class="rt">@ ₹' + l.rate.toFixed(2) + '</div>' +
                    (i === 0 && l.qty > 0 ? '<span class="tagf">FIFO</span>' : '');
      row.appendChild(d);
    });
  }
  function issue(){
    var want = Math.max(1, Math.min(60, parseInt(document.getElementById('qty').value, 10) || 0));
    var total = lots.reduce(function(a,l){ return a + l.qty; }, 0);
    out.innerHTML = '';
    if(want > total){
      out.innerHTML = '<span style="color:var(--rose);font-weight:700">✖ Short of stock — ' + want +
        ' requested, ' + total + ' available. issue_voucher() rolls the whole slip back; nothing is issued.</span>';
      draw(-1);
      return;
    }
    var left = want, step = 0, value = 0;
    (function next(){
      if(left <= 0 || step >= lots.length){
        var tot = document.createElement('span');
        tot.className = 'tot';
        tot.textContent = '→ voucher value ₹' + inr(value) + '  ·  ISS/2026-27/0001';
        out.appendChild(tot);
        draw(-1);
        return;
      }
      var l = lots[step];
      if(l.qty === 0){ step++; next(); return; }
      var take = Math.min(l.qty, left);
      l.qty -= take; left -= take; value += take * l.rate;
      draw(step);
      var ln = document.createElement('div');
      ln.className = 'line';
      ln.textContent = 'stock_out → ' + take + ' × ' + l.lot + ' @ ₹' + l.rate.toFixed(2) + '  = ₹' + inr(take*l.rate);
      out.appendChild(ln);
      step++;
      setTimeout(next, reduce ? 0 : 520);
    })();
  }
  if(row){
    draw(-1);
    document.getElementById('issueBtn').addEventListener('click', issue);
    document.getElementById('resetBtn').addEventListener('click', function(){
      lots = JSON.parse(JSON.stringify(SEED));
      draw(-1);
      out.innerHTML = '<span style="color:var(--dim)">Lots restored. Enter a quantity and press Issue.</span>';
    });
  }

  document.getElementById('yr').textContent = new Date().getFullYear();
})();
</script>
</body>
</html>
