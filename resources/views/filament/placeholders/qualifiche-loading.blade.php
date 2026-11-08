<div style="
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
">
    <div style="font-weight:600; font-size:14px; color:#111827; margin-bottom:16px;">Qualifiche</div>

    @foreach(range(1, 3) as $i)
        <div style="
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            animation: fi-pulse 1.5s ease-in-out infinite;
            animation-delay: {{ ($i - 1) * 0.15 }}s;
        ">
            <div style="height:14px; border-radius:6px; background:#f3f4f6; flex:2;"></div>
            <div style="height:14px; border-radius:6px; background:#f3f4f6; flex:1;"></div>
            <div style="height:14px; border-radius:6px; background:#f3f4f6; width:80px;"></div>
        </div>
    @endforeach

    <div style="margin-top:12px; font-size:12px; color:#9ca3af;">
        Caricamento qualifiche in corso…
    </div>
</div>

<style>
@keyframes fi-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: .4; }
}
</style>
