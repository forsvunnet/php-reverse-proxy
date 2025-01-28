# PHP Single-File Proxy Server

This project provides a simple single-file PHP script to forward local websites to other devices on the same network. It supports dynamic URL rewriting in headers and response bodies.

## Motivation

The goal of this project is to quickly and easily share a local website (e.g., `localhost`, `site.test`) with other devices on the same network. Unlike tools that require setup or installation, this script is a single-file PHP solution with no external dependencies.

---

## Features

- **Search and Replace**: Rewrites all occurrences of the target host in response bodies (e.g., `site.test` → `192.168.0.87:8011`).
- **Header Rewriting**: Dynamically rewrites `Location` and `Set-Cookie` headers to point to the proxy.
- **Single File**: A lightweight, dependency-free PHP script.
---

## Usage

### Prerequisites

- PHP 8.0 or later.

### Starting the Proxy Server

Run the script from the command line, providing the target host (local site) and the proxy address (your device's IP and port):

```
php proxy.php <targetHost> <proxyIp:proxyPort>
```

**Example**:
```
php proxy.php site.test 192.168.0.87:8011
```

This will start a PHP server on `192.168.0.87:8011` that forwards requests to `site.test`.

### Accessing the Proxy

Once the server is running, visit `http://192.168.0.87:8011` on any device in your local network to view the proxied site.

---

## How It Works

### URL and Host Rewriting

The script intercepts requests and performs the following transformations:

1. **Request Forwarding**:
   - Requests to `192.168.0.87:8011` are forwarded to the target host (`site.test`).

2. **Header Rewriting**:
   - Updates `Location` and `Set-Cookie` headers:
     ```
     Location: http://site.test/some-page
     → Location: http://192.168.0.87:8011/some-page
     ```

3. **Response Body Rewriting**:
   - Replaces all occurrences of the target host in the response body:
     ```
     <a href="http://site.test/resource">
     → <a href="http://192.168.0.87:8011/resource">
     ```

---

## Script Details

### Command-Line Arguments

- `targetHost`: The domain or local site to proxy (e.g., `site.test`).
- `proxyIp:proxyPort`: The IP and port for the proxy server.

### Example Workflow

1. Start the proxy server:
   ```
   php proxy.php site.test 192.168.0.87:8011
   ```

2. Visit the proxy on any device:
   ```
   http://192.168.0.87:8011
   ```

3. Links, redirects, and resources are dynamically rewritten to the proxy address.

---

## Features in Depth

### Search and Replace
The script dynamically replaces all references to the target host (`site.test`) in response bodies with the proxy address (`192.168.0.87:8011`). This ensures resources, links, and API calls work seamlessly on other devices.

### Header Rewriting
The following headers are rewritten:
- `Location`: Ensures redirects point to the proxy address.
- `Set-Cookie`: Updates the `Domain` attribute to the proxy.

---

## Limitations

- Only supports HTTP/1.1 (does not handle HTTP/2 or WebSockets).
- Large response bodies may impact performance due to in-memory manipulation.

---

## Example Proxy Script

```
php proxy.php site.test 192.168.0.87:8011
```

### File Contents:
The file `proxy.php` acts as both a CLI starter and router. Here’s an example of its dual functionality:

- **CLI Mode**:
  ```
  php proxy.php site.test 192.168.0.87:8011
  ```

- **Router Mode**:
  The script processes requests dynamically when running as part of the PHP built-in server.

---

## Contributing

Contributions to enhance functionality or improve performance are welcome! Please open an issue or submit a pull request.

---

## License

This project is licensed under the MIT License. See `LICENSE.md` for details.

