<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Add success route after checkout
$content = str_replace(
    "this.addRoute('/checkout', this.renderCheckout.bind(this));",
    "this.addRoute('/checkout', this.renderCheckout.bind(this));\n        this.addRoute('/order-success/:orderId', this.renderOrderSuccess.bind(this));",
    $content
);

// Add renderOrderSuccess method before renderLogin
$successMethod = "
    private async renderOrderSuccess(params: Record<string, string>): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        const orderId = params.orderId;

        content.innerHTML = `
            <div class=\"max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center\">
                <div class=\"mb-8\">
                    <div class=\"w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4\">
                        <svg class=\"w-10 h-10 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5 13l4 4L19 7\"/>
                        </svg>
                    </div>
                    <h1 class=\"text-3xl font-bold text-gray-900 mb-2\">Comanda plasată cu succes!</h1>
                    <p class=\"text-gray-600 mb-4\">
                        Comanda ta #${orderId} a fost înregistrată.
                    </p>
                    <p class=\"text-gray-600\">
                        Vei primi biletele pe email în câteva minute.
                    </p>
                </div>

                <div class=\"space-y-3\">
                    <button onclick=\"window.tixelloRouter.navigate('/events')\" class=\"w-full max-w-xs mx-auto block px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition\">
                        Înapoi la evenimente
                    </button>
                </div>
            </div>
        `;
    }
";

$content = str_replace(
    "    private renderLogin(): void {",
    $successMethod . "\n    private renderLogin(): void {",
    $content
);

file_put_contents($file, $content);

echo "Success route and page added!\n";
