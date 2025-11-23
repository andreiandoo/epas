<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Contract - {{ $tenant->contract_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gray-800 text-white p-6">
                <h1 class="text-2xl font-bold">Sign Contract</h1>
                <p class="text-gray-300">{{ $tenant->contract_number }}</p>
            </div>

            <form id="signatureForm" class="p-6 space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                    <input type="text" name="signer_name" required
                        value="{{ $tenant->contact_first_name }} {{ $tenant->contact_last_name }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Signature</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-2">
                        <canvas id="signaturePad" class="w-full h-48 bg-white rounded cursor-crosshair"></canvas>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" id="clearSignature" class="text-sm text-gray-500 hover:text-gray-700">
                            Clear Signature
                        </button>
                    </div>
                    <input type="hidden" name="signature_data" id="signatureData">
                </div>

                <div class="flex items-start">
                    <input type="checkbox" name="agree_terms" id="agree_terms" required
                        class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="agree_terms" class="ml-2 text-sm text-gray-600">
                        I have read and agree to the terms and conditions outlined in this contract.
                        I understand that this electronic signature has the same legal effect as a handwritten signature.
                    </label>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <a href="{{ route('contract.view', $token) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Back to Contract
                    </a>
                    <button type="submit" id="submitBtn" class="inline-flex items-center px-6 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Sign Contract
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signaturePad');
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)'
        });

        // Resize canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Clear signature
        document.getElementById('clearSignature').addEventListener('click', () => {
            signaturePad.clear();
        });

        // Form submission
        document.getElementById('signatureForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (signaturePad.isEmpty()) {
                alert('Please provide a signature');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing...';

            // Get signature data
            document.getElementById('signatureData').value = signaturePad.toDataURL();

            const formData = new FormData(e.target);

            try {
                const response = await fetch('{{ route("contract.sign.submit", $token) }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Failed to sign contract');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Sign Contract';
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign Contract';
            }
        });
    </script>
</body>
</html>
