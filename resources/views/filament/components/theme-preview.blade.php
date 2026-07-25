<div style="
    --preview-bg: {{ $bg ?? '#101827' }};
    --preview-accent: {{ $accent ?? '#7357ff' }};
    --preview-text: {{ $text ?? '#ffffff' }};
    --preview-surface: color-mix(in srgb, var(--preview-bg) 88%, white);
    --preview-border: color-mix(in srgb, var(--preview-text) 16%, var(--preview-bg));
    background: var(--preview-bg);
    color: var(--preview-text);
    border-radius: .85rem;
    padding: 1rem 1.15rem;
    font-family: system-ui, sans-serif;
    max-width: 22rem;
    border: 1px solid var(--preview-border);
    display: grid;
    gap: .75rem;
">
    {{-- Logo row --}}
    <div style="display:flex;align-items:center;justify-content:space-between;opacity:.6;">
        <span style="font-size:.7rem;font-weight:600;letter-spacing:-.02em">{{ config('app.name') }}</span>
        <svg viewBox="0 0 24 24" style="width:1rem;height:1rem;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><path d="M12 16V3m0 0-4.5 4.5M12 3l4.5 4.5M5 13v6.2c0 .99.81 1.8 1.8 1.8h10.4c.99 0 1.8-.81 1.8-1.8V13"/></svg>
    </div>
    {{-- Avatar + Name --}}
    <div style="display:grid;place-items:center;gap:.35rem;text-align:center">
        <div style="width:2.75rem;height:2.75rem;border-radius:50%;background:var(--preview-accent);display:grid;place-items:center;color:#fff;font-size:1.1rem;font-weight:700;box-shadow:0 4px 12px rgba(0,0,0,.18);border:2px solid color-mix(in srgb, var(--preview-text) 50%, transparent);">И</div>
        <div style="font-weight:700;font-size:.95rem;letter-spacing:-.03em">Иван Иванов</div>
        <div style="font-size:.72rem;opacity:.7">HR-менеджер</div>
    </div>
    {{-- Action button --}}
    <div style="
        display:flex;align-items:center;justify-content:center;gap:.4rem;
        background:var(--preview-accent);color:#fff;border:0;border-radius:.6rem;
        padding:.5rem .75rem;font-size:.78rem;font-weight:700;
    ">
        <svg viewBox="0 0 24 24" style="width:.95rem;height:.95rem;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
        Сохранить контакт
    </div>
    {{-- The tiles mirror the real public contact surfaces, not just the three swatches. --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.45rem">
        <span style="display:grid;place-items:center;min-height:2.25rem;border-radius:.55rem;background:var(--preview-surface);border:1px solid var(--preview-border);font-size:.68rem;font-weight:600;">Telegram</span>
        <span style="display:grid;place-items:center;min-height:2.25rem;border-radius:.55rem;background:var(--preview-surface);border:1px solid var(--preview-border);font-size:.68rem;font-weight:600;">MAX</span>
    </div>
    {{-- Color swatches --}}
    <div style="display:flex;gap:.35rem;justify-content:center;margin-top:.15rem">
        <span style="display:inline-block;width:1rem;height:1rem;border-radius:50%;background:var(--preview-bg);border:1px solid color-mix(in srgb, var(--preview-text) 24%, transparent)" title="Фон"></span>
        <span style="display:inline-block;width:1rem;height:1rem;border-radius:50%;background:var(--preview-accent);border:1px solid color-mix(in srgb, var(--preview-accent) 60%, transparent)" title="Акцент"></span>
        <span style="display:inline-block;width:1rem;height:1rem;border-radius:50%;background:var(--preview-text);border:1px solid color-mix(in srgb, var(--preview-text) 40%, transparent)" title="Текст"></span>
    </div>
</div>
