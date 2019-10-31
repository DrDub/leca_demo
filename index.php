<html>
<head>
<title>Curriculum Alignment in Crisis Context Demo</title>
</head>
<body>

<a href="https://github.com/DrDub/leca_demo"><img
	style="position: absolute; top: 0; right: 0; border: 0;"
	src="https://s3.amazonaws.com/github/ribbons/forkme_right_darkblue_121621.png"
	alt="Fork me on GitHub">
</a>

<h1>Curriculum Alignment in Crisis Context Demo</h1>
<p>See <a href="https://blog.learningequality.org/report-release-design-sprint-on-curriculum-alignment-in-crisis-contexts-57eb717b9e7e">this blog posting for context</a>.</p>

   <p>Every time you refresh this page, you might get two topics from two different curriculum documents, as aligned by the current model. An effort to find a close
   training pair is done, if effective, the pair is also shown, together with metadata about how the pair was annotated. A permalink at the bottom allows to retrieve
   the exact page for later archival, discussion and comparison. <b>Refresh the pair for another pair.</b></p>
<?php
if($has_query = array_key_exists('permalink',$_GET)) {
  $seed = $_GET['permalink'];
}else{
  $seed = rand();
}

srand($seed);

$db = new SQLite3('data.db');

// how many
$count = $db->query("select MAX(rowid) from `standardnodes`");
if(! $count){
  die("no DB");
}
$count = $count->fetchArray()[0];
error_log($count);

// first element
do {
  $rowid = rand(0, $count - 1);
  $stmt = $db->prepare('SELECT * FROM `standardnodes` WHERE rowid = :rowid;');
  $stmt->bindValue(':rowid', $rowid);
  $res = $stmt->execute()->fetchArray();
  $node1 = $res['id'];
  $node1doc = $res['document_id'];
}while(! $node1);
error_log($node1);

// second element, short list using r-tree
$stmt = $db->prepare('SELECT `embeddings_index`.`x`, `embeddings_index`.`y` FROM `embeddings_index` WHERE `id` = :id');
$stmt->bindValue(':id', $node1);
$res = $stmt->execute()->fetchArray();
$node1x = $res['x'];
$node1y = $res['y'];
$stmt = $db->prepare('SELECT `embeddings`.* FROM `embeddings` WHERE `id` = :id');
$stmt->bindValue(':id', $node1);
$res = $stmt->execute()->fetchArray();
$full_embeddings = Array();
for($i=1; $i <= 128; $i++){
  $full_embeddings[] = $res[$i];
}

$stmt = $db->prepare('SELECT `embeddings`.* ' .
  'FROM `embeddings_index`, `embeddings`, `standardnodes` ' .
  'WHERE `embeddings_index`.`id` = `embeddings`.`id` AND `standardnodes`.`id` = `embeddings`.`id` ' .
  'AND `standardnodes`.`document_id` <> :node1doc ' .
  'AND `embeddings_index`.`x` >= :lowx AND `embeddings_index`.`xx` <= :highx '.
  'AND `embeddings_index`.`y` >= :lowy AND `embeddings_index`.`yy` <= :highy ;');
if(! $stmt){
  die($db->lastErrorMsg());
}
$stmt->bindValue(':node1doc', $node1doc);
$stmt->bindValue(':lowx',  $node1x - 1.0);
$stmt->bindValue(':highx', $node1x + 1.0);
$stmt->bindValue(':lowy',  $node1y - 1.0);
$stmt->bindValue(':highy', $node1y + 1.0);
$res = $stmt->execute();

$best_id = -1;
$best_dist = 999999;
$best_embeddings = Array();
$count = 0;
while($res && $count < 100) {
  $row = $res->fetchArray();
  $other_embeddings = Array();
  for($i=1; $i <= 128; $i++){
    $other_embeddings[] = $row[$i];
  }
  $dist = 0;
  for($i=0; $i < 128; $i++){
    $diff = $full_embeddings[$i] - $other_embeddings[$i];
    $dist += $diff * $diff;
  }
  if($dist < $best_dist){
    $best_id = $row[0];
    $best_dist = $dist;
    $best_embeddings = $other_embeddings;
  }
  $count++;
}

// now find a similar judgment pair
$best_pair1_id = -1;
$best_pair2_id = -1;
$best_pair_dist = 999999;
if($best_id > 0) {
  $stmt = $db->prepare('SELECT `embeddings_index`.`x`, `embeddings_index`.`y` FROM `embeddings_index` WHERE `id` = :id');
  $stmt->bindValue(':id', $best_id);
  $res = $stmt->execute()->fetchArray();
  $node2x = $res['x'];
  $node2y = $res['y'];
  
  $stmt = $db->prepare('SELECT `embeddings`.`id` ' .
    'FROM `embeddings_index`, `embeddings`, `standardnodes` ' .
    'WHERE `embeddings_index`.`id` = `embeddings`.`id` AND `standardnodes`.`id` = `embeddings`.`id` ' .
    'AND `standardnodes`.`document_id` = :node1doc AND `standardnodes`.`in_judgments` = 1 ' .
    'AND `embeddings_index`.`x` >= :lowx AND `embeddings_index`.`xx` <= :highx '.
    'AND `embeddings_index`.`y` >= :lowy AND `embeddings_index`.`yy` <= :highy ;');
  if(! $stmt){
    die($db->lastErrorMsg());
  }
  $stmt->bindValue(':node1doc', $node1doc);
  $stmt->bindValue(':lowx',  $node1x - 2.0);
  $stmt->bindValue(':highx', $node1x + 2.0);
  $stmt->bindValue(':lowy',  $node1y - 2.0);
  $stmt->bindValue(':highy', $node1y + 2.0);
  $res = $stmt->execute();

  $count = 0;
  while($res && $count < 10) {
    $row = $res->fetchArray();
    $pair1_id = $row[0];

  
    $stmt = $db->prepare('SELECT `embeddings`.* ' .
      'FROM `embeddings_index`, `embeddings`, `standardnodes`, `humanjudgments` ' .
      'WHERE `embeddings_index`.`id` = `embeddings`.`id` AND `standardnodes`.`id` = `embeddings`.`id` ' .
      'AND ( (`humanjudgments`.`node1_id` = :node1 AND `humanjudgments`.`node2_id` = `embeddings_index`.`id`) '.
      'OR    (`humanjudgments`.`node2_id` = :node1 AND `humanjudgments`.`node1_id` = `embeddings_index`.`id`) ) ' . 
      'AND `standardnodes`.`document_id` <> :node1doc AND `standardnodes`.`in_judgments` = 1 ' .
      'AND `embeddings_index`.`x` >= :lowx AND `embeddings_index`.`xx` <= :highx '.
      'AND `embeddings_index`.`y` >= :lowy AND `embeddings_index`.`yy` <= :highy ;');
    if(! $stmt){
      die($db->lastErrorMsg());
    }
    $stmt->bindValue(':node1doc', $node1doc);
    $stmt->bindValue(':node1', $pair1_id);
    $stmt->bindValue(':lowx',  $node2x - 3.0);
    $stmt->bindValue(':highx', $node2x + 3.0);
    $stmt->bindValue(':lowy',  $node2y - 3.0);
    $stmt->bindValue(':highy', $node2y + 3.0);
    $res2 = $stmt->execute();

    $count2 = 0;
    while($res2 && $count2 < 10) {
      $row = $res2->fetchArray();
      $pair2_id = $row[0];
    
      $other_embeddings = Array();
      for($i=1; $i <= 128; $i++){
        $other_embeddings[] = $row[$i];
      }
      $dist = 0;
      for($i=0; $i < 128; $i++){
        $diff = $best_embeddings[$i] - $other_embeddings[$i];
        $dist += $diff * $diff;
      }
      if($dist < $best_pair_dist){
        $best_pair1_id = $pair1_id;
        $best_pair2_id = $pair2_id;
        $best_pair_dist = $dist;
      }
      $count2++;
    }
    $count++;
  }
}




function shownode($db, $nodeid) {
  $nodes = Array();
  if($nodeid <= 0){
    echo 'Not found';
  }else{
    $current = $nodeid;
    do {
      $stmt = $db->prepare('SELECT * FROM `standardnodes` WHERE id = :id;');
      $stmt->bindValue(':id', $current);
      $res = $stmt->execute()->fetchArray();
      $parent = $res['parent_id'];
      $current = $parent;
    $nodes[]=$res;
    } while($parent);
    
    // get the curriculum document
    $stmt = $db->prepare('SELECT * FROM `curriculumdocuments` WHERE id = :id;');
    $stmt->bindValue(':id', $nodes[0]['document_id']);
    $doc=$stmt->execute()->fetchArray();

    echo '<p>';
    echo 'Curriculum: ' . $doc['title'] . '<br>'; 
    echo 'Country: ' . $doc['country'] . '<br>';
    echo '</p>';

    $indent = "&nbsp; &nbsp;";
    for($i = 0; $i < count($nodes); $i++){
      if($i == count($nodes) - 1){
        echo '<b>';
      }
      echo $indent . $nodes[count($nodes) - $i - 1]['title'];
      if($i == count($nodes) - 1){
        echo '</b>';
      }
      echo '<br>';
      $indent = $indent . "&nbsp; &nbsp;";
    }
  }
}
?>
  
<table>
<tr>
<th>Node 1</th>
<th>Node 2</th>
</tr>
<tr>
<td><?php shownode($db, $node1); ?></td><td><?php shownode($db, $best_id); ?></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<?php
if($best_pair1_id > 0 && $best_pair2_id > 0) {
?>
<tr><td colspan="2" align="center"><b>REASON</b></td></tr>
<td><?php shownode($db, $best_pair1_id); ?></td><td><?php shownode($db, $best_pair2_id); ?></td>
<?php
//    $stmt = $db->prepare('SELECT `humanjudgments`.`user_id` AS `user_id`, `userprofiles`.`background` AS `background`, `userprofiles`.`subject_areas` AS `subject_areas` ' .
    $stmt = $db->prepare('SELECT `humanjudgments`.*, `userprofiles`.`background`, `userprofiles`.`subject_areas` ' .
      'FROM `humanjudgments`, `userprofiles` ' .
      'WHERE `humanjudgments`.`user_id` = `userprofiles`.`id` '.
      'AND ( (`humanjudgments`.`node1_id` = :node1 AND `humanjudgments`.`node2_id` = :node2) '.
      'OR    (`humanjudgments`.`node1_id` = :node2 AND `humanjudgments`.`node2_id` = :node1) );');
    $stmt->bindValue(':node1', $best_pair1_id);
    $stmt->bindValue(':node2', $best_pair2_id);
    $res = $stmt->execute();
    if($res){
      $row = $res->fetchArray();
?>
<tr><td colspan="2" align="center"><br><b>ANNOTATED BY</b></td></tr>
<tr><td colspan="2">
      <ul>
      <li>User-ID: <?php echo $row['user_id']; ?></li>
      <li>User Background: <?php echo $row['background']; ?></li>
      <li>User Subject Areas: <?php echo $row['subject_areas']; ?></li>
      <li>Rating: <?php echo $row['rating']; ?></li>
      <li>Confidence: <?php echo $row['confidence']; ?></li>
      <li>Mode: <?php echo $row['mode']; ?></li>
      <li>Extra: <?php echo $row['extra_fields']; ?></li>
      <li>Judgment-ID: <?php echo $row['id']; ?></li>
      </ul>
</td>
</tr>
<?php      
    }
  }
?>
</table>

<?php
echo '<a href="/?permalink=' . $seed . '">Permalink</a>';
?>
</body>
</html>