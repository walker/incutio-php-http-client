# About

This is a continuation of the Incutio PHP HTTP Client class. It's still one of the better standalone HTTP client classes out there. I'm just going to make it do more.

Of all I can find on the HTTP spec, body content is allowed with POST, PUT, and DELETE requests, so we allow you to send body content when using this client.

## Instantiation

	$client = new HttpClient('www.domain.com', 80)
	
Where the first argument is the domain and the second is the port. The port is optional and default to 80 if not provided.

## GET

`$data` can be an object or array.
`$headers` should be an array

	$client->get('/service/endpoint', $data, $headers)

## POST

`$data` can be a string, object, or array.
`$headers` should be an array

	$client->post('/service/endpoint', $data, $headers)

## PUT

`$data` can be a string, object, or array.
`$headers` should be an array

	$client->put('/service/endpoint', $data, $headers)

## DELETE

`$data` can be a string, object, or array.
`$headers` should be an array

	$client->put('/service/endpoint', $data, $headers)

## Headers

Content-Type on requests is no longer set by default.

If Accept is not set, it defaults to:

	'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*'

Headers should be things like:

	array('Accept'=>'application/json', 'Content-Type':'text/xml', 'etc')

`$data` and `$headers` are optional in all of these.

## Referer

The Incutio library used to set the referer of a call as the last item called. I rarely find this helpful, so it's turned off by default now. To turn it back on:

	$this->client->setPersistReferers(true);
