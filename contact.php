<?php
require __DIR__ . '/inc.php';
guard_query([]);
$S = site(); $msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $n = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
    $em = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $tx = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 3000);
    if (!empty($_POST['web'])) { $msg = 'تم الإرسال.'; $ok = true; }
    elseif (!rl('c' . ip(), 3, 600)) $msg = 'محاولات كثيرة، أعد المحاولة لاحقاً.';
    elseif ($n === '' || !$em || $tx === '') $msg = 'يرجى ملء جميع الحقول بشكل صحيح.';
    else {
        inbox_add('contact', $n, $em, $tx);
        $msg = 'شكراً لتواصلك معنا، تم إرسال رسالتك بنجاح وسنرد عليك قريباً.'; $ok = true;
    }
}
seo_set(['canonical' => abs_url('contact.php'), 'desc' => 'تواصل مع إدارة ' . seo_name() . ' عبر نموذج الاتصال الرسمي.']);
head('راسلنا - ' . seo_name());
?>
<div class="max-w-xl w-full mx-auto my-auto bg-white/10 backdrop-blur-md border border-white/20 p-8 rounded-3xl shadow-2xl space-y-6">
<h1 class="text-2xl font-black text-center">راسل الإدارة</h1>
<?php if ($S['footer']['contact_intro'] !== ''): ?><p class="text-xs text-red-100 leading-relaxed"><?= nl2br(e($S['footer']['contact_intro'])) ?></p><?php endif; ?>
<?php if ($msg): ?><div class="bg-black/30 p-3 rounded-xl text-center text-xs font-bold <?= $ok ? 'text-emerald-300' : 'text-amber-300' ?>"><?= e($msg) ?></div><?php endif; ?>
<form method="POST" class="space-y-4 text-xs">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="text" name="web" class="hidden" tabindex="-1" autocomplete="off">
<div><label class="block mb-1 text-gray-200">الاسم الكامل</label><input type="text" name="name" required maxlength="80" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white"></div>
<div><label class="block mb-1 text-gray-200">البريد الإلكتروني</label><input type="email" name="email" required class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white" dir="ltr"></div>
<div><label class="block mb-1 text-gray-200">نص الرسالة</label><textarea name="message" rows="4" required maxlength="3000" class="w-full bg-black/30 border border-white/20 rounded-xl p-2.5 text-white"></textarea></div>
<button type="submit" class="w-full bg-white text-red-900 font-extrabold py-2.5 rounded-xl hover:bg-gray-100 transition cursor-pointer">إرسال الرسالة</button>
</form>
<div class="text-center pt-2"><a href="index.php" data-track="back_home" class="text-xs text-red-200 hover:underline">العودة للرئيسية</a></div>
</div>
<?php foot();
