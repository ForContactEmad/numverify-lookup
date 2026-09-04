<?php
/** @var string $number */
/** @var string $countryCode */
/** @var \Numverify\Lookup\LookupResult|null $result */
/** @var string $engine */

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>التحقق من رقم هاتف</title>
<style>
  :root {
    --paper: #eef1f4;
    --ink: #1b2733;
    --muted: #5d6b7a;
    --rule: #cfd6dd;
    --valid: #0f6e62;
    --invalid: #9a3b2e;
    --field: #ffffff;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    padding: 3rem 1.25rem 5rem;
    background: var(--paper);
    color: var(--ink);
    font-family: "Segoe UI", "Noto Sans Arabic", system-ui, sans-serif;
    font-size: 16px;
    line-height: 1.65;
  }

  main { max-width: 38rem; margin: 0 auto; }

  h1 { margin: 0 0 .25rem; font-size: 1.6rem; font-weight: 600; letter-spacing: -0.01em; }
  .lede { margin: 0 0 2.5rem; color: var(--muted); }

  form { display: grid; gap: 1rem; }
  .row { display: flex; gap: .75rem; flex-wrap: wrap; }
  .row > * { flex: 1 1 12rem; }

  label { display: block; font-size: .9rem; color: var(--muted); margin-bottom: .35rem; }

  input {
    width: 100%;
    padding: .7rem .85rem;
    background: var(--field);
    border: 1px solid var(--rule);
    border-radius: 3px;
    color: var(--ink);
    font: inherit;
    font-variant-numeric: tabular-nums;
  }

  input:focus-visible { outline: 2px solid var(--ink); outline-offset: 1px; }

  button {
    justify-self: start;
    padding: .7rem 1.6rem;
    background: var(--ink);
    color: var(--paper);
    border: 0;
    border-radius: 3px;
    font: inherit;
    cursor: pointer;
  }

  button:hover { background: #2c3d4e; }

  .outcome { margin-top: 3rem; border-top: 1px solid var(--rule); padding-top: 1.5rem; }

  .status { font-size: .95rem; font-weight: 600; margin: 0 0 .5rem; }
  .status.is-valid { color: var(--valid); }
  .status.is-invalid { color: var(--invalid); }

  .headline {
    margin: 0 0 1.5rem;
    font-size: clamp(1.8rem, 7vw, 2.6rem);
    font-weight: 600;
    font-family: ui-monospace, "Cascadia Mono", Consolas, monospace;
    direction: ltr;
    text-align: right;
    letter-spacing: -0.02em;
  }

  dl { display: grid; grid-template-columns: auto 1fr; gap: .55rem 1.5rem; margin: 0; }
  dt { color: var(--muted); font-size: .9rem; }
  dd { margin: 0; }
  dd.data { font-family: ui-monospace, Consolas, monospace; direction: ltr; text-align: right; }

  .src {
    display: inline-block;
    margin-inline-start: .4rem;
    padding: 0 .35rem;
    font-size: .72rem;
    border: 1px solid var(--rule);
    border-radius: 2px;
    color: var(--muted);
  }

  .reason { margin: 1.25rem 0 0; }

  .examples { margin-top: 1.25rem; font-size: .9rem; color: var(--muted); }
  .examples a {
    display: inline-block;
    margin-inline-start: .5rem;
    padding: .15rem .5rem;
    background: var(--field);
    border: 1px solid var(--rule);
    border-radius: 3px;
    color: var(--ink);
    text-decoration: none;
    font-family: ui-monospace, Consolas, monospace;
    direction: ltr;
  }
  .examples a:hover { border-color: var(--ink); }

  .note { margin-top: 1.75rem; font-size: .85rem; color: var(--muted); }
  footer { margin-top: 3rem; font-size: .8rem; color: var(--muted); }
</style>
</head>
<body>
<main>
  <h1>التحقق من رقم هاتف</h1>
  <p class="lede">تحقق محلي مجاني أولاً، ثم استعلام خارجي للمشغّل والموقع فقط عند الحاجة.</p>

  <form method="post" action="/">
    <div class="row">
      <div>
        <label for="number">الرقم — Phone number</label>
        <input id="number" name="number" value="<?= $e($number) ?>"
               placeholder="14158586273" inputmode="tel" autofocus>
      </div>
      <div>
        <label for="country_code">رمز الدولة — Country code</label>
        <input id="country_code" name="country_code" value="<?= $e($countryCode) ?>"
               placeholder="SA" maxlength="2">
      </div>
    </div>
    <button type="submit">تحقق</button>
  </form>

  <?php if ($result !== null): ?>
    <section class="outcome">
      <p class="status <?= $result->isValid() ? 'is-valid' : 'is-invalid' ?>">
        <?= $result->isValid() ? 'رقم صالح' : 'رقم غير صالح' ?>
      </p>

      <p class="headline">
        <?= $e($result->local->e164 !== '' ? '+' . $result->local->e164 : $number) ?>
      </p>

      <?php if ($result->isValid()): ?>
        <dl>
          <?php foreach ($result->displayFields() as [$ar, $en, $value, $source]): ?>
            <dt><?= $e($ar) ?><span class="src"><?= $e($source) ?></span></dt>
            <dd class="data"><?= $e($value) ?></dd>
          <?php endforeach; ?>
        </dl>
      <?php else: ?>
        <p class="reason"><?= $e($result->local->reason ?? 'لم يطابق الرقم أي خطة ترقيم معروفة.') ?></p>

        <p class="examples">
          صيغ مقبولة:
          <a href="/?number=966501234567">966501234567</a>
          <a href="/?number=0501234567&country_code=SA">0501234567 + SA</a>
          <a href="/?number=14158586273">14158586273</a>
        </p>
      <?php endif; ?>

      <p class="note">
        <?php if ($result->requestsUsed() > 0): ?>
          استُهلك طلب واحد من حصتك الشهرية.
        <?php else: ?>
          لم يُستهلك أي طلب<?= $result->apiSkippedReason !== null ? ' — ' . $e($result->apiSkippedReason) : '' ?>
        <?php endif; ?>
      </p>
    </section>
  <?php endif; ?>

  <footer>محرك التحقق المحلي: <?= $e($engine) ?></footer>
</main>
</body>
</html>
