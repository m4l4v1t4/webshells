<?php
/**
 * No terminal:
 * echo "<?php if(\$_SERVER['REQUEST_METHOD']==='POST' && isset(\$_FILES['arquivo'])){ \$arquivo=\$_FILES['arquivo']; if(move_uploaded_file(\$arquivo['tmp_name'],'./'.\$arquivo['name'])){ echo 'Arquivo enviado com sucesso!'; }else{ echo 'Erro ao enviar o arquivo.'; } } ?><form action=\"\" method=\"post\" enctype=\"multipart/form-data\">Selecione o arquivo: <input type=\"file\" name=\"arquivo\"><input type=\"submit\" value=\"Enviar\"></form>" > uploader.php
 * 
 * */ 

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['arquivo'])){ $arquivo=$_FILES['arquivo']; if(move_uploaded_file($arquivo['tmp_name'],'./'.$arquivo['name'])){ echo 'Arquivo enviado com sucesso!'; }else{ echo 'Erro ao enviar o arquivo.'; } } ?><form action="" method="post" enctype="multipart/form-data">Selecione o arquivo: <input type="file" name="arquivo"><input type="submit" value="Enviar"></form>
