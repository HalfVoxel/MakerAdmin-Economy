<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Models
use App\Models\Account as AccountModel;
use App\Models\Transaction;

// TODO: Remove
use App\Traits\AccountingPeriod;
use App\Traits\Pagination;

class Account extends Controller
{
	use Pagination;

	/**
	 * Returns the masterledger
	 *
	 * The masterledger is basically a list of accounts, but we only show accounts with a balance != 0
	 */
	public function masterledger(Request $request, $accountingperiod)
	{
		// Check that the specified accounting period exists
		$this->_getAccountingPeriodId($accountingperiod);

		// Return all account that have a balance not equal to 0
		$filters = [
			"transactions"     => [">", 0],
			"accountingperiod" => ["=", $accountingperiod],
		];

		// Sorting
		if(!empty($request->get("sort_by")))
		{
			$order = ($request->get("sort_order") == "desc" ? "desc" : "asc");
			$filters["sort"] = [$request->get("sort_by"), $order];
		}

		// Return all account that have a balance not equal to 0
		return AccountModel::list($filters);
	}

	/**
	 * Returns a list of accounts
	 */
	public function list(Request $request, $accountingperiod)
	{
		// Check that the specified accounting period exists
		$this->_getAccountingPeriodId($accountingperiod);

		// Paging filter
		$filters = [
			"per_page" => $this->per_page($request), // TODO: Rename?
		];

		// Filter on relations
		if(!empty($request->get("relations")))
		{
			$filters["relations"] = $request->get("relations");
		}

		// Filter on search
		if(!empty($request->get("search")))
		{
			$filters["search"] = $request->get("search");
		}

		// Sorting
		if(!empty($request->get("sort_by")))
		{
			$order = ($request->get("sort_order") == "desc" ? "desc" : "asc");
			$filters["sort"] = [$request->get("sort_by"), $order];
		}

		// Return json array
		return AccountModel::list($filters);
	}

	/**
	 * Create account
	 */
	public function create(Request $request, $accountingperiod)
	{
		$json = $request->json()->all();

		// Check that the specified accounting period exists
		$accountingperiod_id = $this->_getAccountingPeriodId($accountingperiod);

		// We need to check that the provided account number is not in conflict with an existing one
		if($this->_accountNumberIsExisting($accountingperiod, $json["account_number"]))
		{
			return Response()->json([
				"status"  => "error",
				"message" => "The specified account number does already exist",
			], 409);
		}

		// Create new entity
		$entity = new AccountModel;
		$entity->account_number      = $json["account_number"] ?? null;
		$entity->title               = $json["title"]          ?? null;
		$entity->description         = $json["description"]    ?? null;
		$entity->accountingperiod_id = $accountingperiod_id;

		// Validate input
		$entity->validate();

		// Save the entity
		$entity->save();

		// Send response to client
		return Response()->json([
			"status" => "created",
			"data" => $entity->toArray(),
		], 201);
	}

	/**
	 * Returns an single account
	 */
	public function read(Request $request, $accountingperiod, $account_number)
	{
		// Check that the specified accounting period exists
		$this->_getAccountingPeriodId($accountingperiod);

		// Load the account
		$entity = AccountModel::load([
			"accountingperiod" => $accountingperiod,
			"account_number"   => ["=", $account_number],
		]);

		// Generate an error if there is no such account
		if(false === $entity)
		{
			return Response()->json([
				"message" => "No account with specified account number in the selected accounting period",
			], 404);
		}
		else
		{
			// Send response to client
			return Response()->json([
				"data" => $entity->toArray(),
			], 200);
		}
	}

	/**
	 *
	 */
	public function update(Request $request, $accountingperiod, $id)
	{
		return ['error' => 'not implemented'];
	}

	/**
	 *
	 */
	public function delete(Request $request, $accountingperiod, $account_number)
	{
		// Check that the specified accounting period exists
		$this->_getAccountingPeriodId($accountingperiod);

		// Load the account
		$entity = AccountModel::load([
			["accountingperiod", "=", $accountingperiod],
			["account_number",   "=", $account_number],
		]);

		if($entity->delete())
		{
			return [
				"status" => "deleted"
			];
		}
		else
		{
			return [
				"status" => "error"
			];
		}
	}

	/**
	 * TODO: Kolla om kontot finns redan
	 */
	public function _accountNumberIsExisting($account_number)
	{
		return false;
	}


	/**
	 *
	 */
	public function transactions(Request $request, $accountingperiod, $account_number)
	{
		// Check that the specified accounting period exists
		$accountingperiod_id = $this->_getAccountingPeriodId($accountingperiod);

		// Paging filter
		$filters = [
			"per_page" => $this->per_page($request),
			"account_number" => $account_number,
			"accountingperiod" => $accountingperiod,
		];

/*
		// Filter on relations
		if($request->get("relations"))
		{
			$filters["relations"] = $request->get("relations");
		}
*/

		// Filter on search
		if(!empty($request->get("search")))
		{
			$filters["search"] = $request->get("search");
		}

		// Sorting
		if(!empty($request->get("sort_by")))
		{
			$order = ($request->get("sort_order") == "desc" ? "desc" : "asc");
			$filters["sort"] = [$request->get("sort_by"), $order];
		}

/*
		// Filters
		if(!empty($request->get("account_number")))
		{
			$filters["account_number"] = ["=", $request->get("account_number")];
		}
*/


		// Load data from database
		$result = Transaction::list($filters);

		// Return json array
		return $result;
	}
}