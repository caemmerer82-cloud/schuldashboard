<?php
function jwt_encode(array $payload, string $secret): string {
    $header = base64url_encode(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $body   = base64url_encode(json_encode($payload));
    $sig    = base64url_encode(hash_hmac('sha256',"$header.$body",$secret,true));
    return "$header.$body.$sig";
}
function jwt_decode(string $token, string $secret): array {
    $parts = explode('.', $token);
    if (count($parts)!==3) throw new RuntimeException('JWT: Ungültiges Format');
    [$header,$body,$sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256',"$header.$body",$secret,true));
    if (!hash_equals($expected,$sig)) throw new RuntimeException('JWT: Ungültige Signatur');
    $payload = json_decode(base64url_decode($body),true);
    if (!is_array($payload)) throw new RuntimeException('JWT: Payload ungültig');
    if (isset($payload['exp'])&&$payload['exp']<time()) throw new RuntimeException('JWT: Token abgelaufen');
    return $payload;
}
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data),'+/','-_'),'=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data,'-_','+/').str_repeat('=',(4-strlen($data)%4)%4));
}
function jwt_from_request(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION']??'';
    if (str_starts_with($h,'Bearer ')) return trim(substr($h,7));
    return $_COOKIE['jwt']??null;
}
