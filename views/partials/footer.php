<?php // views/partials/footer.php ?>
  </div><!-- /page-content -->
</div><!-- /main-wrap -->

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/public/js/app.js?v=2"></script>
<?php if(!empty($pageJs)): ?>
<script src="<?= Security::e($pageJs) ?>?v=2"></script>
<?php endif; ?>
<?php if(!empty($inlineJs)): ?>
<script><?= $inlineJs ?></script>
<?php endif; ?>
</body>
</html>
