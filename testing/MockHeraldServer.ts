/**
 * MockHeraldServer — Test utility for Herald Bundle
 *
 * Simulates the Herald API for integration tests:
 * 1. Receives sendMessage requests (POST /public/api/v1/inbound/{endpointId})
 * 2. Returns a conversationId immediately
 * 3. Sends webhook callbacks to the app with HMAC signature
 *
 * The webhook lifecycle is: started → completed (or failed).
 * Configurable response, delay, and failure mode.
 *
 * @example
 * ```typescript
 * const herald = new MockHeraldServer({
 *   webhookUrl: 'http://host.docker.internal:8080/public/herald/webhook',
 *   webhookSecret: 'test-secret',
 * });
 * await herald.start();
 *
 * // ... run tests that call Herald API at herald.getApiUrl() ...
 *
 * await herald.stop();
 * ```
 */

import http from 'node:http';
import crypto from 'node:crypto';

export interface MockHeraldServerOptions {
  /** URL where the app receives Herald webhooks */
  webhookUrl: string;
  /** HMAC SHA-256 secret for signing webhooks (must match app config) */
  webhookSecret: string;
  /** Default response text for completed conversations */
  defaultResponse?: string;
  /** Delay in ms before sending webhooks (simulates processing time) */
  webhookDelayMs?: number;
  /** If true, conversations fail instead of completing */
  failMode?: boolean;
  /** Failure reason when failMode is true */
  failureReason?: string;
  /** Stack name returned in webhooks */
  stackName?: string;
  /** Primary model name returned in usage */
  primaryModel?: string;
}

export interface MockConversation {
  id: string;
  endpointId: string;
  message: string;
  systemMessages: string[];
  metadata: Record<string, unknown>;
  createdAt: Date;
}

export class MockHeraldServer {
  private server: http.Server | null = null;
  private port = 0;
  private conversations: MockConversation[] = [];
  private webhooksSent: Array<{ conversationId: string; event: string; statusCode: number }> = [];
  private readonly options: Required<MockHeraldServerOptions>;

  constructor(options: MockHeraldServerOptions) {
    this.options = {
      defaultResponse: 'Thank you for contacting us. We have received your inquiry and will process it shortly. If you have any further questions, please do not hesitate to reach out.',
      webhookDelayMs: 100,
      failMode: false,
      failureReason: 'Mock failure for testing',
      stackName: 'Mock Herald Stack',
      primaryModel: 'mock-model',
      ...options,
    };
  }

  async start(): Promise<number> {
    return new Promise((resolve, reject) => {
      this.server = http.createServer((req, res) => this.handleRequest(req, res));
      this.server.on('error', reject);
      this.server.listen(0, '0.0.0.0', () => {
        const addr = this.server!.address();
        if (addr && typeof addr === 'object') {
          this.port = addr.port;
          resolve(this.port);
        } else {
          reject(new Error('Failed to get server address'));
        }
      });
    });
  }

  async stop(): Promise<void> {
    return new Promise((resolve) => {
      if (this.server) {
        this.server.close(() => resolve());
        this.server = null;
      } else {
        resolve();
      }
    });
  }

  /**
   * API URL to configure in HeraldClient.
   * Uses host.docker.internal so the app container can reach the host.
   */
  getApiUrl(): string {
    return `http://host.docker.internal:${this.port}`;
  }

  getPort(): number {
    return this.port;
  }

  /** All conversations received */
  getConversations(): MockConversation[] {
    return [...this.conversations];
  }

  /** All webhooks sent */
  getWebhookLog(): Array<{ conversationId: string; event: string; statusCode: number }> {
    return [...this.webhooksSent];
  }

  /** Set fail mode on/off (can be changed between tests) */
  setFailMode(fail: boolean, reason?: string): void {
    (this.options as any).failMode = fail;
    if (reason) {
      (this.options as any).failureReason = reason;
    }
  }

  /** Set the default response text */
  setDefaultResponse(response: string): void {
    (this.options as any).defaultResponse = response;
  }

  /** Set webhook delay */
  setWebhookDelay(ms: number): void {
    (this.options as any).webhookDelayMs = ms;
  }

  /** Wait until all pending webhooks have been sent */
  async waitForWebhooks(timeoutMs = 10_000): Promise<void> {
    const start = Date.now();
    while (this.webhooksSent.length < this.conversations.length * 2) {
      if (Date.now() - start > timeoutMs) {
        throw new Error(`Timeout waiting for webhooks (sent: ${this.webhooksSent.length}, expected: ${this.conversations.length * 2})`);
      }
      await new Promise((r) => setTimeout(r, 50));
    }
  }

  /** Reset state between tests */
  reset(): void {
    this.conversations = [];
    this.webhooksSent = [];
  }

  private handleRequest(req: http.IncomingMessage, res: http.ServerResponse): void {
    const url = req.url ?? '';

    // Match POST /public/api/v1/inbound/{endpointId}
    const inboundMatch = url.match(/^\/public\/api\/v1\/inbound\/([^/?]+)/);

    if (req.method === 'POST' && inboundMatch) {
      this.handleSendMessage(inboundMatch[1], req, res);
      return;
    }

    // Health check
    if (req.method === 'GET' && url === '/public/api/v1/health') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ status: 'ok' }));
      return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
  }

  private handleSendMessage(endpointId: string, req: http.IncomingMessage, res: http.ServerResponse): void {
    let body = '';
    req.on('data', (chunk: Buffer) => { body += chunk.toString(); });

    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        const conversationId = this.generateConversationId();

        const conversation: MockConversation = {
          id: conversationId,
          endpointId,
          message: data.message ?? '',
          systemMessages: data.systemMessages ?? [],
          metadata: data.metadata ?? {},
          createdAt: new Date(),
        };

        this.conversations.push(conversation);

        // Return conversation immediately
        res.writeHead(202, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
          id: conversationId,
          stackId: 'mock-stack-id',
          status: 'active',
          metadata: { clientMetadata: data.metadata ?? {} },
          createdAt: conversation.createdAt.toISOString(),
          updatedAt: conversation.createdAt.toISOString(),
          messages: [],
        }));

        // Send webhooks asynchronously
        this.scheduleWebhooks(conversation);
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON body' }));
      }
    });
  }

  private scheduleWebhooks(conversation: MockConversation): void {
    const delay = this.options.webhookDelayMs;

    // Send "started" after short delay
    setTimeout(() => {
      this.sendWebhook(conversation, 'conversation.started', 'active', null);
    }, delay);

    // Send "completed" or "failed" after longer delay
    setTimeout(() => {
      if (this.options.failMode) {
        this.sendWebhook(conversation, 'conversation.failed', 'idle', this.options.failureReason);
      } else {
        this.sendWebhook(conversation, 'conversation.completed', 'idle', null);
      }
    }, delay * 2);
  }

  private sendWebhook(
    conversation: MockConversation,
    event: string,
    status: string,
    failureReason: string | null,
  ): void {
    const isCompleted = event === 'conversation.completed';
    const isFailed = event === 'conversation.failed';

    const payload: Record<string, unknown> = {
      conversationId: conversation.id,
      nodeId: 'mock-agent',
      stackId: 'mock-stack-id',
      stackName: this.options.stackName,
      status,
      event,
      clientMetadata: conversation.metadata,
      response: isCompleted ? this.options.defaultResponse : null,
      failureReason: isFailed ? failureReason : null,
      usage: (isCompleted || isFailed) ? {
        inputTokens: 150,
        outputTokens: 80,
        inputCost: '0.0005',
        outputCost: '0.0003',
        totalCost: '0.0008',
        llmCalls: 1,
        primaryModel: this.options.primaryModel,
        generationTimeMs: 1200,
      } : undefined,
    };

    const body = JSON.stringify(payload);
    const signature = crypto
      .createHmac('sha256', this.options.webhookSecret)
      .update(body)
      .digest('hex');

    const url = new URL(this.options.webhookUrl);

    const options: http.RequestOptions = {
      hostname: url.hostname,
      port: url.port,
      path: url.pathname,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
        'X-Herald-Signature': signature,
      },
    };

    const req = http.request(options, (res) => {
      this.webhooksSent.push({
        conversationId: conversation.id,
        event,
        statusCode: res.statusCode ?? 0,
      });
    });

    req.on('error', (err) => {
      this.webhooksSent.push({
        conversationId: conversation.id,
        event,
        statusCode: -1,
      });
    });

    req.write(body);
    req.end();
  }

  private generateConversationId(): string {
    return `mock-${Date.now()}-${Math.random().toString(36).substring(2, 8)}`;
  }
}
