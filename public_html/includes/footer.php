<?php /** @var array|null $user */ ?>
<?php if ($user): ?>
        </div>
        <p class="av-footer-note"><?= e(APP_NAME) ?> &middot; IESTP Benjamín Franklin - Moquegua, Perú</p>
    </div>
</div>
<?php else: ?>
<p class="av-footer-note"><?= e(APP_NAME) ?> &middot; IESTP Benjamín Franklin - Moquegua, Perú</p>
<?php endif; ?>
<script>
// Tabs (Contenido / Tareas)
document.querySelectorAll('.av-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var group = tab.closest('.av-tabs-wrap') || document;
        group.querySelectorAll('.av-tab').forEach(function (t) { t.classList.remove('active'); });
        group.querySelectorAll('.av-tabpanel').forEach(function (p) { p.classList.remove('active'); });
        tab.classList.add('active');
        var target = document.getElementById(tab.dataset.tabTarget);
        if (target) target.classList.add('active');
    });
});
// Accordion (unidades)
document.querySelectorAll('.av-accordion-header').forEach(function (header) {
    header.addEventListener('click', function () {
        header.closest('.av-accordion-item').classList.toggle('open');
    });
});
</script>
</body>
</html>
