<!DOCTYPE html>
<html>
<head>
    <title>Employee List</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        h1 { color: #006A4E; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Generated on: {{ $date }}</p>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>NID</th>
                <th>Designation</th>
                <th>Station</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $emp)
            <tr>
                <td>{{ $emp->id }}</td>
                <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
                <td>{{ $emp->nid_number }}</td>
                <td>{{ $emp->designation->title ?? 'N/A' }}</td>
                <td>{{ $emp->office->name ?? 'N/A' }}</td>
                <td>{{ ucfirst($emp->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>