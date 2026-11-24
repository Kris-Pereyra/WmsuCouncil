<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
public_start('Student council finance, made transparent');
$in = !empty($_SESSION['account_id']);
?>
<section class="hero"><div class="wrap hero-grid">
  <div class="hero-copy">
    <h1>Know where every peso of the student council goes.</h1>
    <p class="lead">Council Funds keeps collections, budgets, projects and expenses in one place. It is open to every verified student and managed by the officers you elected.</p>
    <div class="actions">
      <?php if ($in): ?>
        <a class="btn" href="dashboard.php">Open dashboard</a>
      <?php else: ?>
        <a class="btn" href="signup.php">Create your account</a>
        <a class="btn ghost" href="login.php">Log in</a>
      <?php endif; ?>
    </div>
    <ul class="hero-points">
      <li><?= icon('shield') ?>Verified school emails only</li>
      <li><?= icon('inbox') ?>College Mayor approval for funding</li>
    </ul>
  </div>

  <aside class="ledgercard" aria-label="Example of the dashboard">
    <div class="lc-top"><span>Fund balance</span><em>Example data</em></div>
    <strong class="lc-num">₱18,299.50</strong>
    <div class="split"><i style="width:40%"></i></div>
    <div class="lc-rows">
      <div><span>Collected</span><b class="pos">₱30,450.00</b></div>
      <div><span>Spent</span><b class="neg">₱12,150.50</b></div>
    </div>
    <div class="lc-req">
      <div><span class="pill pending">Pending</span><strong>Leadership Training</strong><small>₱25,000.00 requested</small></div>
      <div><span class="pill approved">Approved</span><strong>Intramurals</strong><small>₱60,000.00 requested</small></div>
    </div>
  </aside>
</div></section>

<section class="section" id="features"><div class="wrap">
  <h2 class="sec-title">Everything the council needs, nothing it doesn’t</h2>
  <div class="grid3">
    <article class="feat"><span class="ico"><?= icon('shield') ?></span><h3>Verified students only</h3>
      <p>Sign up with your <code><?= e(ALLOWED_EMAIL_DOMAIN) ?></code> email and confirm it with a one-time code. No code, no account.</p></article>
    <article class="feat"><span class="ico"><?= icon('wallet') ?></span><h3>Live fund balance</h3>
      <p>Total collected, total spent and what is left for each project, always calculated from the records and never typed in by hand.</p></article>
    <article class="feat"><span class="ico"><?= icon('inbox') ?></span><h3>Funding requests</h3>
      <p>Officers request money for an event. Officers submit proposals to their College Mayor. Decisions and reasons are kept with the request.</p></article>
    <article class="feat"><span class="ico"><?= icon('book') ?></span><h3>Full ledger</h3>
      <p>Every peso in and out on one page. Filter by date or type to prepare reports.</p></article>
    <article class="feat"><span class="ico"><?= icon('badge') ?></span><h3>Real profiles</h3>
      <p>Profile picture, college, program, school year and contact number, plus the council position each student holds.</p></article>
    <article class="feat"><span class="ico"><?= icon('users') ?></span><h3>Role-based access</h3>
      <p>Students, officers and the administrator each see and do only what their role allows.</p></article>
  </div>
</div></section>

<section class="section alt" id="how"><div class="wrap">
  <h2 class="sec-title">Get started in three steps</h2>
  <ol class="steps">
    <li><b>1</b><h3>Sign up</h3><p>Enter your name, program and school details, and your <code><?= e(ALLOWED_EMAIL_DOMAIN) ?></code> email.</p></li>
    <li><b>2</b><h3>Enter the code</h3><p>We email you a 6-digit code. Type it in to prove the email is yours.</p></li>
    <li><b>3</b><h3>Log in</h3><p>See the dashboard and your profile, including any council position you hold.</p></li>
  </ol>
</div></section>

<section class="section" id="roles"><div class="wrap">
  <h2 class="sec-title">Who can do what</h2>
  <div class="grid3">
    <article class="role"><h3>Student</h3><ul>
      <li>View their enrolled school year and announcements</li><li>See their own annual fees and payment history</li><li>Upload payment proof and update their own profile</li></ul></article>
    <article class="role"><h3>Council officer</h3><ul>
      <li>Verify payment proofs and publish college announcements</li><li>View college transaction history</li><li>Submit event, project and funding requests to the Mayor</li><li>Cannot approve their own fund allocation</li></ul></article>
    <article class="role"><h3>College Mayor</h3><ul><li>Manage only their assigned college</li><li>Approve or decline officer requests</li><li>Assign, reassign and end officer terms</li><li>Manage college budgets and projects</li></ul></article>
    <article class="role"><h3>Administrator</h3><ul><li>University-wide access across all colleges</li><li>Assign and reassign College Mayors</li><li>Manage the university central fund</li><li>Publish university-wide announcements</li></ul></article>
  </div>
</div></section>

<section class="cta"><div class="wrap">
  <h2>Ready to see where the money goes?</h2>
  <?php if ($in): ?>
    <a class="btn" href="dashboard.php">Open dashboard</a>
  <?php else: ?>
    <a class="btn" href="signup.php">Create your account</a>
  <?php endif; ?>
</div></section>
<?php public_end();
