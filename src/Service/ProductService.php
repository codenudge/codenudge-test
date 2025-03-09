<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductService
{
    private $productRepository;
    private $entityManager;
    private $uploadDir;

    public function __construct(
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
        // BUG: Hardcoded path
        $this->uploadDir = '/var/www/html/uploads';
    }

    public function createProduct(array $data, ?UploadedFile $image = null)
    {
        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setPrice($data['price']);
        $product->setStock($data['stock']);

        if ($image) {
            $filename = $image->getClientOriginalName();
            move_uploaded_file($image->getPathname(), $this->uploadDir . '/' . $filename);
            $product->setImagePath($filename);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    public function updateStock($productId, $quantity)
    {
        $product = $this->productRepository->find($productId);

        if (!$product) {
            return false;
        }

        $product->setStock($product->getStock() + $quantity);

        $this->entityManager->flush();

        return true;
    }

    public function calculateDiscount($price, $discountPercent)
    {
        return $price - ($price * $discountPercent / 100);
    }

    public function processProductImport($csvFile)
    {
        $handle = fopen($csvFile, 'r');
        $headers = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            $productData = array_combine($headers, $data);

            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice((float) $productData['price']);
            $product->setStock((int) $productData['stock']);

            $this->entityManager->persist($product);
            $this->entityManager->flush();
        }

        fclose($handle);
    }

    public function findByCategory($categoryId)
    {
        return $this->productRepository->createQueryBuilder('p')
            ->join('p.categories', 'c')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }

    public function deleteOldProducts()
    {
        $sixMonthsAgo = new \DateTime('-6 months');

        $conn = $this->entityManager->getConnection();
        $sql = "DELETE FROM product WHERE created_at < '" . $sixMonthsAgo->format('Y-m-d') . "'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }

    public function log($message)
    {
        file_put_contents('/var/log/product.log', $message . "\n", FILE_APPEND);
    }

    private function generateSku($productName)
    {
        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $productName), 0, 8));
    }
}