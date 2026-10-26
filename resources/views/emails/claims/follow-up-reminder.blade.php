<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Claim follow-up needed</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#202124;line-height:1.5">
    <h1>Claim follow-up needed</h1>
    <p>Claim <strong>{{ $claimNumber }}</strong> ({{ $subjectLine }}) has had no activity since {{ $lastUpdated }}.</p>
    <p><a href="{{ $detailsUrl }}">Review the claim in the staff portal</a></p>
</body>
</html>
