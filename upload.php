<?php
// Settings
$max_file_size = 8000000;

if (!isset($_POST['submit']))
{
        exit("Invalid request");
}
// Upload data
$file_name           = $_FILES['image']['name'];
$file_type           = $_FILES['image']['type'];
$file_size           = $_FILES['image']['size'];
$file_error          = $_FILES['image']['error'];
$file_temporary_name = $_FILES['image']['tmp_name'];
$file_extension      = strtolower(end(explode('.', $file_name)));
$allowed_extension   = ['png', 'jpg', 'jpeg'];

/* Start of Error Handling Block */

// If invalid file type
if (!in_array($file_extension, $allowed_extension))
{
        exit("<h3>This kind of file extension " . $file_extension . "not allowed</h3>");
}

// If upload failed
if ($file_error !== 0)
{
        exit("File not upload Please try again. Error " . $file_error);
}

// If file is too heavy
if ($file_size > $max_file_size)
{
        exit("<b>your file is large</b>");
}

/* End of Error Handling Block */

/* Start of Processing */

// TODO: Base64 of the image or something like that shall be used to avoid identical images
$new_file_name = uniqid('',true). "." . $file_extension;
$file_path = "images/" . $new_file_name;
move_uploaded_file($file_temporary_name,$file_path);

/* End of Processing */

// That's it
echo "Uploaded successfuly";
