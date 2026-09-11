<?php
$monthNames = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
$monthLabel = $monthNames[substr($analytics['month'],5,2)] . ' de ' . substr($analytics['month'],0,4);
$colors = ['#007f8b','#6954b5','#d06a20','#2578ba','#bc4475','#548230','#94702b','#595fbb','#aa4c35','#388570','#7d587b','#597180'];
?>
<section class="card analytics-card">
<div class="analytics-heading"><div><span class="eyebrow">ANÁLISIS MENSUAL</span><h2>Atenciones por especialidad</h2><p class="muted">Distribución de las atenciones registradas en las historias clínicas cargadas.</p></div>
<form method="get" class="analytics-filter"><input type="hidden" name="page" value="analytics"><label>Mes de atención<input type="month" name="month" min="1000-01" max="9998-12" value="<?= h($analytics['month']) ?>" required></label><button class="button primary">Consultar</button></form></div>
<p class="muted">Se usa la fecha de atención extraída del PDF. Cada atención cuenta una vez, aunque tenga varios anexos o versiones. Incluye historias retiradas; no mide solicitudes de correo ni citas pendientes.</p>
<?php if (!$analytics['total']): ?>
<div class="empty-state"><h3>No hay atenciones registradas en <?= h($monthLabel) ?></h3><p>Selecciona otro mes o carga las historias clínicas correspondientes.</p></div>
<?php else: ?>
<div class="analytics-summary"><strong><?= (int)$analytics['total'] ?></strong><span>atenciones · <?= h($monthLabel) ?></span><span><?= count($analytics['rows']) ?> especialidades</span></div>
<div class="analytics-layout">
<figure class="analytics-figure"><svg class="analytics-pie" viewBox="0 0 360 360" role="img" aria-labelledby="analytics-chart-title analytics-chart-description">
<title id="analytics-chart-title">Porcentaje de atenciones por especialidad en <?= h($monthLabel) ?></title><desc id="analytics-chart-description">Distribución de <?= (int)$analytics['total'] ?> atenciones. Los valores completos se encuentran en la tabla.</desc>
<?php $angle=-M_PI/2; foreach ($analytics['rows'] as $i=>$row):
    $end=$angle+2*M_PI*$row['total']/$analytics['total'];
    $color=$colors[$i%count($colors)];
    $label=$row['name'].': '.number_format($row['percentage'],1,',','.').'% ('.$row['total'].' atenciones)';
    $num=fn(float $v)=>number_format($v,4,'.','');
    if (count($analytics['rows'])===1): ?>
<circle cx="180" cy="180" r="156" fill="<?= $color ?>"><title><?= h($label) ?></title></circle>
<?php else: $path='M 180 180 L '.$num(180+156*cos($angle)).' '.$num(180+156*sin($angle)).' A 156 156 0 '.(($end-$angle)>M_PI?'1':'0').' 1 '.$num(180+156*cos($end)).' '.$num(180+156*sin($end)).' Z'; ?>
<path d="<?= $path ?>" fill="<?= $color ?>" stroke="white" stroke-width="2"><title><?= h($label) ?></title></path>
<?php endif; if ($row['percentage']>=6): $mid=($angle+$end)/2; ?>
<text x="<?= count($analytics['rows'])===1?'180':$num(180+105*cos($mid)) ?>" y="<?= count($analytics['rows'])===1?'180':$num(180+105*sin($mid)) ?>" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="17" font-weight="700"><?= number_format($row['percentage'],1,',','.') ?>%</text>
<?php endif; $angle=$end; endforeach ?>
</svg><figcaption>Participación en el total del mes. Porcentajes redondeados a un decimal.</figcaption></figure>
<div class="table-wrap"><table class="analytics-table"><caption>Especialidades ordenadas de mayor a menor cantidad de atenciones</caption><thead><tr><th scope="col">Especialidad</th><th scope="col">Atenciones</th><th scope="col">Porcentaje</th></tr></thead><tbody>
<?php foreach($analytics['rows'] as $i=>$row): ?><tr><th scope="row"><svg width="12" height="12" aria-hidden="true"><rect width="12" height="12" rx="3" fill="<?= $colors[$i%count($colors)] ?>"/></svg> <?= h($row['name']) ?></th><td><?= $row['total'] ?></td><td><strong><?= number_format($row['percentage'],1,',','.') ?>%</strong></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?></section>
