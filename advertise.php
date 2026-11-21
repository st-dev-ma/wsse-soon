<?php
require __DIR__ . '/inc.php';
guard_query([]);
$S = site(); $f = $S['footer']; $msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $n = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
    $co = mb_substr(trim((string)($_POST['company'] ?? '')), 0, 80);
    $ph = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
    $em = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $tx = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 3000);
    if (!empty($_POST['web'])) { $msg = 'تم الإرسال.'; $ok = true; }
    elseif (!rl('c' . ip(), 3, 600)) $msg = 'محاولات كثيرة، أعد المحاولة لاحقاً.';
    elseif ($n === '' || !$em || $tx === '') $msg = 'يرجى ملء الاسم والبريد والرسالة بشكل صحيح.';
    else { inbox_add('ad', $n, $em, $tx, ['company' => $co, 'phone' => $ph]); $msg = 'تم استلام طلبك الإعلاني، وسنتواصل معك قريباً.'; $ok = true; }
}
seo_set(['canonical' => abs_url('advertise.php'), 'desc' => 'أعلن في موقع ' . seo_name() . ' الرسمي واصل إلى جمهور المباريات والنتائج.']);
head('أعلن معنا - ' . seo_name());
?>
<div class="max-w-xl w-full mx-auto my-auto bg-white/10 backdrop-blur-md border border-white/20 p-8 rounded-3xl shadow-2xl space-y-6">
<h1 class="text-2xl font-black text-center">أعلن معنا في الموقع</h1>
<?php if ($f['advertise_text'] !== ''): ?><p class="text-xs text-red-100 leading-relaxed"><?= nl2br(e($f['advertise_text'])) ?></p><?php endif; ?>
<?php if ($msg): ?><div class="bg-black/30 p-3 rounded-xl text-center text-xs font-bold <?= $ok ? 'text-emerald-300' : 'text-amber-300' ?>"><?= e($msg) ?></div><?php endif; ?>
<form method="POST" class="space-y-4 text-xs">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="text" name="web" class="hidden" tabindex="-1" autocomplete="off">
<div class="grid grid-cols-2 gap-3">
<div><label class="block mb-1 text-gray-200">الاسم</label><input type="text" name="name" required maxlength="80" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white"></div>
<div><label class="block mb-1 text-gray-200">الشركة / العلامة (اختياري)</label><input type="text" name="company" maxlength="80" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white"></div>
<div><label class="block mb-1 text-gray-200">البريد الإلكتروني</label><input type="email" name="email" required class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white" dir="ltr"></div>
<div><label class="block mb-1 text-gray-200">الهاتف (اختياري)</label><input type="tel" name="phone" maxlength="30" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white" dir="ltr"></div>
</div>
<div><label class="block mb-1 text-gray-200">تفاصيل الإعلان المطلوب</label><textarea name="message" rows="4" required maxlength="3000" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white"></textarea></div>
<button type="submit" class="w-full bg-amber-400 text-red-950 font-extrabold py-2.5 rounded-xl hover:bg-amber-300 transition cursor-pointer">إرسال الطلب</button>
</form>
<?php if (filter_var($f['email'], FILTER_VALIDATE_EMAIL)): ?>
<div class="bg-black/20 p-4 rounded-2xl text-xs"><p>أو راسلنا مباشرة: <a href="mailto:<?= e($f['email']) ?>" data-track="ad_mail" class="font-mono text-white" dir="ltr"><?= e($f['email']) ?></a></p></div>
<?php endif; ?>
<div class="text-center pt-2"><a href="index.php" data-track="back_home" class="text-xs text-red-200 hover:underline">العودة للرئيسية</a></div>
</div>
<?php foot();
