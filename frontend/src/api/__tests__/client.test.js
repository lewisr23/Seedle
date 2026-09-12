import { describe, expect, it, vi } from 'vitest';
import { ApiError, api, getToken, setToken } from '../client';

/** Build a fetch stub that records its call and returns the given response. */
function stubFetch({ status = 200, body = '', json } = {}) {
  const text = json !== undefined ? JSON.stringify(json) : body;
  const fetchMock = vi.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    text: () => Promise.resolve(text),
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

function lastCall(fetchMock) {
  const [url, init] = fetchMock.mock.calls.at(-1);
  return { url: new URL(url), init };
}

describe('token storage', () => {
  it('round-trips a token through localStorage', () => {
    expect(getToken()).toBeNull();
    setToken('abc123');
    expect(getToken()).toBe('abc123');
  });

  it('clears the token when set to a falsy value', () => {
    setToken('abc123');
    setToken(null);
    expect(getToken()).toBeNull();
  });
});

describe('request building', () => {
  it('sends Accept but no Content-Type when there is no body', async () => {
    const fetchMock = stubFetch({ json: { data: [] } });

    await api('/products');

    const { init } = lastCall(fetchMock);
    expect(init.headers.Accept).toBe('application/json');
    expect(init.headers['Content-Type']).toBeUndefined();
    expect(init.body).toBeUndefined();
  });

  it('serialises the body and sets Content-Type when one is given', async () => {
    const fetchMock = stubFetch({ json: { data: {} } });

    await api('/posts', { method: 'POST', body: { body: 'hello' } });

    const { init } = lastCall(fetchMock);
    expect(init.method).toBe('POST');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(JSON.parse(init.body)).toEqual({ body: 'hello' });
  });

  it('attaches the bearer token only once one is stored', async () => {
    const fetchMock = stubFetch({ json: {} });

    await api('/me');
    expect(lastCall(fetchMock).init.headers.Authorization).toBeUndefined();

    setToken('tok-42');
    await api('/me');
    expect(lastCall(fetchMock).init.headers.Authorization).toBe('Bearer tok-42');
  });

  it('appends query params, dropping empty ones', async () => {
    const fetchMock = stubFetch({ json: { data: [] } });

    await api('/products', {
      params: { q: 'tomato', category: '', sun: undefined, zone: null, page: 2, min_price: 0 },
    });

    const { url } = lastCall(fetchMock);
    expect(url.searchParams.get('q')).toBe('tomato');
    expect(url.searchParams.get('page')).toBe('2');
    // Zero is a meaningful filter value and must survive the empty-value check.
    expect(url.searchParams.get('min_price')).toBe('0');
    expect(url.searchParams.has('category')).toBe(false);
    expect(url.searchParams.has('sun')).toBe(false);
    expect(url.searchParams.has('zone')).toBe(false);
  });
});

describe('file uploads', () => {
  it('sends FormData as-is and leaves Content-Type to the browser', async () => {
    const fetchMock = stubFetch({ status: 201, json: { path: 'product-images/a.jpg' } });
    const formData = new FormData();
    formData.append('image', new Blob(['x'], { type: 'image/jpeg' }), 'a.jpg');

    await api('/product-images', { method: 'POST', formData });

    const { init } = lastCall(fetchMock);
    expect(init.body).toBe(formData);
    // Setting it by hand would omit the multipart boundary and break the upload.
    expect(init.headers['Content-Type']).toBeUndefined();
  });

  it('still attaches the bearer token to an upload', async () => {
    setToken('tok-9');
    const fetchMock = stubFetch({ status: 201, json: {} });

    await api('/product-images', { method: 'POST', formData: new FormData() });

    expect(lastCall(fetchMock).init.headers.Authorization).toBe('Bearer tok-9');
  });
});

describe('response handling', () => {
  it('returns parsed JSON on success', async () => {
    stubFetch({ json: { data: [{ id: 1, title: 'Tomato seeds' }] } });

    await expect(api('/products')).resolves.toEqual({
      data: [{ id: 1, title: 'Tomato seeds' }],
    });
  });

  it('returns null for an empty body rather than throwing', async () => {
    stubFetch({ status: 204, body: '' });

    await expect(api('/posts/1/like', { method: 'DELETE' })).resolves.toBeNull();
  });

  it('surfaces Laravel field errors from a 422', async () => {
    stubFetch({
      status: 422,
      json: { message: 'The given data was invalid.', errors: { email: ['Email is required.'] } },
    });

    const err = await api('/register', { method: 'POST', body: {} }).catch((e) => e);

    expect(err).toBeInstanceOf(ApiError);
    expect(err.status).toBe(422);
    expect(err.message).toBe('The given data was invalid.');
    expect(err.errors.email).toEqual(['Email is required.']);
  });

  it('falls back to a status message when the error body has none', async () => {
    stubFetch({ status: 500, body: '<html>Server Error</html>' });

    const err = await api('/products').catch((e) => e);

    expect(err).toBeInstanceOf(ApiError);
    expect(err.status).toBe(500);
    expect(err.message).toBe('Request failed (500)');
    expect(err.errors).toBeUndefined();
  });
});
