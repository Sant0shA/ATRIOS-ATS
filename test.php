<?php
echo "Test file works!";
echo "<br>Token from URL: " . ($_GET['token'] ?? 'none');
?>
```

Put this in root, deploy, and visit:
`https://atrios.in/recruitment-ats/test.php?token=123`

Should show:
```
Test file works!
Token from URL: 123