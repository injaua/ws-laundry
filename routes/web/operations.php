<?php

use App\Http\Controllers\CourierJobController;
use App\Http\Controllers\CourierRouteController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryLotController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\MachineLoadController;
use App\Http\Controllers\MaintenanceJobController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
| Operations: production floor, logistics and courier routes, resources (equipment, stock, maintenance, purchases).
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Production floor: stage board, machine loads, quality control, rework, delays
Route::middleware("can:work-counter")->prefix("production")->name("production.")->group(function () {
    Route::get("/", [ProductionController::class, "board"])->name("board");
    Route::get("/promise", [ProductionController::class, "promise"])->name("promise");
    Route::post("/orders/{order}/advance", [ProductionController::class, "advance"])->name("advance");
    Route::post("/orders/{order}/qc", [ProductionController::class, "qc"])->name("qc");
    Route::post("/orders/{order}/rework", [ProductionController::class, "rework"])->name("rework");
    Route::post("/orders/{order}/reschedule", [ProductionController::class, "reschedule"])->name("reschedule");
    Route::post("/orders/{order}/photos", [ProductionController::class, "storePhotos"])->name("photos.store");
    Route::delete("/orders/{order}/photos/{photo}", [ProductionController::class, "destroyPhoto"])->name("photos.destroy");
    Route::put("/orders/{order}/intake", [ProductionController::class, "updateIntake"])->name("intake");
    Route::get("/loads", [MachineLoadController::class, "index"])->name("loads.index");
    Route::get("/loads/create", [MachineLoadController::class, "create"])->name("loads.create");
    Route::post("/loads", [MachineLoadController::class, "store"])->name("loads.store");
    Route::get("/loads/{load}", [MachineLoadController::class, "show"])->name("loads.show");
    Route::post("/loads/{load}/finish", [MachineLoadController::class, "finish"])->name("loads.finish");
    Route::post("/loads/{load}/cancel", [MachineLoadController::class, "cancel"])->name("loads.cancel");
    Route::get("/report", [ProductionController::class, "report"])->middleware("can:manage-store")->name("report");
});

Route::middleware("can:work-counter")->prefix("logistics")->name("logistics.")->group(function () {
    Route::get("/", [CourierJobController::class, "index"])->name("index");
    Route::get("/create", [CourierJobController::class, "create"])->name("create");
    Route::post("/", [CourierJobController::class, "store"])->name("store");
    Route::get("/route-sheet", [CourierJobController::class, "routeSheet"])->name("route-sheet");
    Route::get("/cash", [CourierJobController::class, "cash"])->name("cash");
    Route::post("/cash/{user}", [CourierJobController::class, "receiveCash"])->name("cash.receive");
    Route::get("/{courierJob}", [CourierJobController::class, "show"])->name("show");
    Route::put("/{courierJob}", [CourierJobController::class, "update"])->name("update");
    Route::post("/{courierJob}/confirm", [CourierJobController::class, "confirm"])->name("confirm");
    Route::post("/{courierJob}/cancel", [CourierJobController::class, "cancel"])->name("cancel");
});

// The courier's route and the actions on the road (staff may record them on the courier's behalf)
Route::middleware("can:drive-routes")->group(function () {
    Route::get("/my-route", [CourierRouteController::class, "index"])->name("courier.route");
    Route::post("/logistics/{courierJob}/start", [CourierRouteController::class, "start"])->name("logistics.start");
    Route::post("/logistics/{courierJob}/complete", [CourierRouteController::class, "complete"])->name("logistics.complete");
    Route::post("/logistics/{courierJob}/fail", [CourierRouteController::class, "fail"])->name("logistics.fail");
});

// Resource management (equipments, stock, maintenance) - admin & owner
Route::middleware("can:manage-store")->prefix("resources")->name("resources.")->group(function () {
    Route::post("/maintenance/{maintenance}/parts", [MaintenanceJobController::class, "addPart"])->name("maintenance.parts.add");
    Route::delete("/maintenance/{maintenance}/parts/{part}", [MaintenanceJobController::class, "removePart"])->name("maintenance.parts.remove");
    Route::patch("/maintenance/{maintenance}/cancel", [MaintenanceJobController::class, "cancel"])->name("maintenance.cancel");
    Route::resource("maintenance", MaintenanceJobController::class);
    Route::post("/items/{item}/movements", [InventoryMovementController::class, "store"])->name("movements.store");
    Route::get("/movements/{movement}/edit", [InventoryMovementController::class, "edit"])->name("movements.edit");
    Route::put("/movements/{movement}", [InventoryMovementController::class, "update"])->name("movements.update");
    Route::delete("/movements/{movement}", [InventoryMovementController::class, "destroy"])->name("movements.destroy");
    Route::get("/items", [InventoryItemController::class, "index"])->name("items.index");
    Route::get("/items/create", [InventoryItemController::class, "create"])->name("items.create");
    Route::post("/items", [InventoryItemController::class, "store"])->name("items.store");
    Route::get("/items/{item}", [InventoryItemController::class, "show"])->name("items.show");
    Route::get("/items/{item}/edit", [InventoryItemController::class, "edit"])->name("items.edit");
    Route::put("/items/{item}", [InventoryItemController::class, "update"])->name("items.update");
    Route::patch("/items/{item}", [InventoryItemController::class, "update"]);
    Route::delete("/items/{item}", [InventoryItemController::class, "destroy"])->name("items.destroy");
    Route::resource("equipments", EquipmentController::class);
    Route::post("/equipments/{equipment}/recipes", [EquipmentController::class, "storeRecipe"])->name("equipments.recipes.store");
    Route::delete("/equipments/{equipment}/recipes/{recipe}", [EquipmentController::class, "destroyRecipe"])->name("equipments.recipes.destroy");
    Route::get("/lots", [InventoryLotController::class, "index"])->name("lots.index");
    Route::resource("suppliers", SupplierController::class)->except(["index", "show"]);
    Route::patch("/purchases/{purchase}/order", [PurchaseOrderController::class, "order"])->name("purchases.order");
    Route::patch("/purchases/{purchase}/cancel", [PurchaseOrderController::class, "cancel"])->name("purchases.cancel");
    Route::post("/purchases/{purchase}/items/{item}/receive", [PurchaseOrderController::class, "receive"])->name("purchases.receive");
    Route::resource("purchases", PurchaseOrderController::class)->except(["index", "show"]);
});

// Suppliers and purchase orders: consultation for the accountant (accounts payable)
// (after the group above, so /resources/purchases/create is matched first)
Route::middleware("can:manage-finance")->prefix("resources")->name("resources.")->group(function () {
    Route::resource("suppliers", SupplierController::class)->only(["index", "show"]);
    Route::resource("purchases", PurchaseOrderController::class)->only(["index", "show"]);
    Route::get("/purchases/{purchase}/document", [PurchaseOrderController::class, "document"])->name("purchases.document");
});
