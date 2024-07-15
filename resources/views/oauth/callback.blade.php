<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>

    <script>
        window.opener.postMessage({
            token: "{{ $token }}"
        }, "https://thanywhere.com")

        window.close()
    </script>
</head>

<body>

</body>

</html>
