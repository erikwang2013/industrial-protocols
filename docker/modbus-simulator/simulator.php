<?php
// Simple Modbus TCP simulator
// Responds to FC 0x03 (Read Holding Registers) and FC 0x06 (Write Single Register)

$host = '0.0.0.0';
$port = 502;

$server = stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Failed to create server: [$errno] $errstr\n");
    exit(1);
}

echo "Modbus Simulator listening on $host:$port\n";

// Simulated register values
$registers = [
    0 => 42,    // 40001 -> 42
    1 => 100,   // 40002 -> 100
    2 => 255,   // 40003 -> 255
];

while ($client = @stream_socket_accept($server, -1)) {
    echo "Client connected\n";
    stream_set_timeout($client, 5);

    while (!feof($client)) {
        $request = @fread($client, 260);
        if ($request === false || $request === '') break;

        $tid    = substr($request, 0, 2);
        $unitId = ord($request[6]);
        $funcCode = ord($request[7]);

        echo "Request: TID=" . bin2hex($tid) . " Unit=$unitId FC=$funcCode\n";

        switch ($funcCode) {
            case 0x03: // Read Holding Registers
                $startAddr = unpack('n', substr($request, 8, 2))[1];
                $quantity  = unpack('n', substr($request, 10, 2))[1];
                echo "  Read Holding: start=$startAddr count=$quantity\n";

                $data = '';
                for ($i = 0; $i < $quantity; $i++) {
                    $val = $registers[$startAddr + $i] ?? 0;
                    $data .= pack('n', $val);
                }
                $byteCount = chr(strlen($data));
                $response = $tid . pack('n', 0) . pack('n', 3 + strlen($data))
                          . chr($unitId) . chr(0x03) . $byteCount . $data;
                fwrite($client, $response);
                break;

            case 0x06: // Write Single Register
                $addr  = unpack('n', substr($request, 8, 2))[1];
                $value = unpack('n', substr($request, 10, 2))[1];
                $registers[$addr] = $value;
                echo "  Write: addr=$addr value=$value\n";
                // Echo back as response
                fwrite($client, $request);
                break;

            default:
                echo "  Unsupported function code: $funcCode\n";
                // Exception response
                $response = $tid . pack('n', 0) . pack('n', 3)
                          . chr($unitId) . chr($funcCode | 0x80) . chr(0x01);
                fwrite($client, $response);
        }
    }

    fclose($client);
    echo "Client disconnected\n";
}
